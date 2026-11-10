<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * One search box for the whole system: matters (reference, title, officer, notes...) and
 * the words inside scanned documents. Both lists already respect each account's section limits,
 * because they are read through the same models as every other page.
 */
class SearchController extends Controller
{
    private const MATTER_COLUMNS = [
        'matter_reference', 'title', 'section', 'category', 'district',
        'assigned_officer', 'related_department', 'summary', 'last_action', 'next_action',
    ];

    private const DOCUMENT_COLUMNS = [
        'original_name', 'category', 'extracted_text', 'classification_reason',
    ];

    private const LIMIT = 30;

    public function index(Request $request)
    {
        $query = trim(preg_replace('/\s+/u', ' ', (string) $request->query('q', '')));
        $query = mb_substr($query, 0, 100);

        $terms = array_slice(array_values(array_filter(explode(' ', $query), fn ($term) => mb_strlen($term) >= 1)), 0, 6);

        $matters = collect();
        $documents = collect();
        $mattersTotal = 0;
        $documentsTotal = 0;
        $tooShort = $query !== '' && mb_strlen($query) < 2;

        if ($terms !== [] && ! $tooShort) {
            $matterQuery = $this->matching(Matter::query(), self::MATTER_COLUMNS, $terms);
            $mattersTotal = (clone $matterQuery)->count();
            $matters = $matterQuery->orderByDesc('updated_at')->limit(self::LIMIT)->get();

            $documentQuery = $this->matching(Document::query()->with('matter'), self::DOCUMENT_COLUMNS, $terms);
            $documentsTotal = (clone $documentQuery)->count();
            $documents = $documentQuery->orderByDesc('classified_at')->limit(self::LIMIT)->get()
                ->each(fn (Document $document) => $document->setAttribute('snippet', $this->snippet($document, $terms)));
        }

        return view('search', [
            'query' => $query,
            'tooShort' => $tooShort,
            'matters' => $matters,
            'documents' => $documents,
            'mattersTotal' => $mattersTotal,
            'documentsTotal' => $documentsTotal,
            'limit' => self::LIMIT,
        ]);
    }

    /**
     * Every word must appear somewhere in the row (in any of the columns), in any case.
     * "%", "_" and "\" typed by the user are searched for literally.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $terms
     */
    private function matching(Builder $builder, array $columns, array $terms): Builder
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';

        foreach ($terms as $term) {
            $pattern = '%'.addcslashes($term, '%_\\').'%';

            $builder->where(function (Builder $group) use ($columns, $pattern, $pgsql) {
                foreach ($columns as $column) {
                    $table = $group->getModel()->getTable();
                    $sql = $pgsql
                        ? "{$table}.{$column} ILIKE ?"
                        : "{$table}.{$column} LIKE ? ESCAPE '\\'";

                    $group->orWhereRaw($sql, [$pattern]);
                }
            });
        }

        return $builder;
    }

    /**
     * A short piece of the scanned text around the first word that matched, with the words marked.
     * The text is escaped first, so nothing in a scanned letter can add markup to the page.
     *
     * @param  list<string>  $terms
     */
    private function snippet(Document $document, array $terms): ?HtmlString
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $document->extracted_text));

        if ($text === '') {
            return null;
        }

        $position = null;

        foreach ($terms as $term) {
            $found = mb_stripos($text, $term);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        if ($position === null) {
            return null;
        }

        $start = max(0, $position - 70);
        $piece = mb_substr($text, $start, 200);
        $piece = ($start > 0 ? '… ' : '').$piece.(mb_strlen($text) > $start + 200 ? ' …' : '');

        $safe = e($piece);

        // One pass with all the words together, so a mark is never placed inside another mark.
        $escaped = array_map(fn ($term) => preg_quote(e($term), '/'), $terms);
        usort($escaped, fn ($a, $b) => strlen($b) <=> strlen($a));

        $safe = preg_replace('/('.implode('|', $escaped).')/iu', '<mark>$1</mark>', $safe) ?? $safe;

        return new HtmlString($safe);
    }
}
