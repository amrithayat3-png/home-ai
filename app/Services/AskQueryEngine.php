<?php

namespace App\Services;

use App\Http\Controllers\DocumentController;
use App\Models\Document;
use App\Models\Matter;
use App\Reminders\DeadlineStage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Answers questions about matters and documents with exact database queries.
 *
 * No AI writes anything here. Every reference number, name, date and count in a
 * reply is read straight from a record or counted by the database. A question that
 * does not fit a known query gets "I can't answer that", never a guess.
 *
 * Because it uses the Matter and Document models, the section limits of the signed-in
 * account apply automatically.
 */
class AskQueryEngine
{
    public const LIMIT = 50;

    private const BRIEF_LIMIT = 10;

    private const LINK_LIMIT = 15;

    private const PRIORITIES = ['Critical', 'High', 'Normal', 'Low'];

    private const DEADLINES = ['overdue', 'today', 'next_7_days', 'next_30_days', 'none', 'overdue_or_next_7_days'];

    private const GROUPS = [
        'section' => 'section',
        'status' => 'status',
        'priority' => 'priority',
        'district' => 'district',
        'officer' => 'assigned_officer',
        'department' => 'related_department',
        'category' => 'category',
    ];

    private const PRIORITY_ORDER = "CASE priority WHEN 'Critical' THEN 0 WHEN 'High' THEN 1 WHEN 'Normal' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END";

    /**
     * @param  array<string, mixed>  $plan  intent plus filters, as chosen by the planner
     * @return array{answer: string, references: array<int, array<string, string>>, documents: array<int, array<string, mixed>>}
     */
    public function run(array $plan): array
    {
        try {
            return match ($plan['intent'] ?? 'unknown') {
                'list_matters' => $this->listMatters($plan),
                'count_matters' => $this->countMatters($plan),
                'get_matter' => $this->getMatter($plan),
                'briefing' => $this->briefing(),
                'list_documents' => $this->listDocuments($plan),
                'document_text' => $this->documentText($plan),
                default => $this->unknown(),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->reply($exception->getMessage());
        }
    }

    // ---------------------------------------------------------------- matters

    private function listMatters(array $plan): array
    {
        [$query, $understood] = $this->matterQuery($plan['filters'] ?? []);

        $total = (clone $query)->count();
        $matters = $this->ordered($query)->limit(self::LIMIT)->get();
        $today = $this->today();

        if ($total === 0) {
            return $this->reply($understood."\n\nNo matters match.");
        }

        $lines = $matters->map(fn (Matter $matter) => $this->matterLine($matter, $today))->implode("\n");

        $count = $total === 1 ? '1 matter found.' : $total.' matters found.';

        if ($total > self::LIMIT) {
            $count .= ' Showing the first '.self::LIMIT.' of '.$total.'.';
        }

        return $this->reply($understood."\n".$count."\n\n".$lines, $matters);
    }

    private function countMatters(array $plan): array
    {
        [$query, $understood] = $this->matterQuery($plan['filters'] ?? []);

        $total = (clone $query)->count();
        $text = $understood."\nTotal: ".$total.' '.($total === 1 ? 'matter.' : 'matters.');

        $group = $plan['group_by'] ?? null;

        if (is_string($group) && trim($group) !== '' && strtolower(trim($group)) !== 'null') {
            $column = self::GROUPS[strtolower(trim($group))] ?? null;

            if ($column === null) {
                throw new InvalidArgumentException('I can group by section, status, priority, district, officer, department or category.');
            }

            $rows = (clone $query)
                ->select([$column.' as grp', DB::raw('COUNT(*) as total')])
                ->groupBy($column)
                ->get()
                ->map(fn ($row) => ['label' => $row->grp === null || $row->grp === '' ? '(not set)' : (string) $row->grp, 'total' => (int) $row->total])
                ->sortBy([['total', 'desc'], ['label', 'asc']])
                ->values();

            $text .= "\n\nBy ".strtolower(trim($group)).":\n".$rows->map(fn ($row) => '- '.$row['label'].': '.$row['total'])->implode("\n");
        }

        return $this->reply($text);
    }

    private function getMatter(array $plan): array
    {
        $reference = $this->reference($plan['filters']['reference'] ?? $plan['reference'] ?? null);

        if ($reference === null) {
            throw new InvalidArgumentException('Please give the matter reference number, for example HM-2026-004.');
        }

        $matter = Matter::where('matter_reference', $reference)->first();

        if (! $matter) {
            return $this->reply('No matter with the reference '.$reference.' is in the records available to your account.');
        }

        $today = $this->today();

        $field = fn (string $label, mixed $value) => $label.': '.(blank($value) ? 'not recorded' : trim((string) $value));

        $text = collect([
            $matter->matter_reference.': '.$matter->title,
            $field('Section', $matter->section),
            $field('Category', $matter->category),
            $field('Priority', $matter->priority),
            $field('Status', $matter->status),
            $field('District', $matter->district),
            $field('Assigned officer', $matter->assigned_officer),
            $field('Related department', $matter->related_department),
            $field('Received', $matter->received_date ? substr((string) $matter->received_date, 0, 10) : null),
            'Deadline: '.$this->dueText($matter, $today),
            $field('Summary', $matter->summary),
            $field('Last action', $matter->last_action),
            $field('Next action', $matter->next_action),
        ])->implode("\n");

        $documents = $matter->documents()->get();

        $text .= "\n\nLinked documents: ".($documents->isEmpty()
            ? 'none'
            : "\n".$documents->map(fn (Document $document) => '- DOC-'.$document->id.' | '.$document->original_name.' | '.$document->category.' | '.$document->review_status)->implode("\n"));

        return $this->reply($text, collect([$matter]), $documents);
    }

    private function briefing(): array
    {
        $today = $this->today();
        $weekEnd = $this->weekEnd($today);
        $active = fn () => Matter::query()->whereIn('status', ['Open', 'Pending']);

        $sections = [
            'Overdue' => $active()->whereNotNull('deadline')->where('deadline', '<', $today),
            'Due within 7 days (including today)' => $active()->whereNotNull('deadline')->whereBetween('deadline', [$today, $weekEnd]),
            'High or Critical priority' => $active()->whereIn('priority', ['High', 'Critical']),
            'Waiting for executive direction' => $active()->where('next_action', 'like', '%executive direction%'),
        ];

        $text = 'Briefing for '.Carbon::parse($today)->format('l, j F Y').'. Active matters only (Open and Pending).';
        $shown = collect();

        foreach ($sections as $title => $query) {
            $total = (clone $query)->count();
            $rows = $this->ordered($query)->limit(self::BRIEF_LIMIT)->get();
            $shown = $shown->concat($rows);

            $text .= "\n\n".$title.': '.$total;

            if ($rows->isNotEmpty()) {
                $text .= "\n".$rows->map(fn (Matter $matter) => $this->matterLine($matter, $today))->implode("\n");
            }

            if ($total > self::BRIEF_LIMIT) {
                $text .= "\nShowing the first ".self::BRIEF_LIMIT.' of '.$total.'.';
            }
        }

        return $this->reply($text, $shown->unique('id'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Builder, 1: string}
     */
    private function matterQuery(array $filters): array
    {
        $query = Matter::query();
        $parts = [];
        $today = $this->today();

        $status = $this->status($filters['status'] ?? null);

        if ($status === 'Active') {
            $query->whereIn('status', ['Open', 'Pending']);
            $parts[] = 'active matters (Open and Pending)';
        } elseif ($status === 'Any') {
            $parts[] = 'all matters, including closed';
        } else {
            $query->where('status', $status);
            $parts[] = strtolower($status).' matters';
        }

        if (($section = $this->section($filters['section'] ?? null)) !== null) {
            $query->where('section', $section);
            $parts[] = 'section '.$section;
        }

        if (($priority = $this->priority($filters['priority'] ?? null)) !== null) {
            if ($priority === 'High_or_Critical') {
                $query->whereIn('priority', ['High', 'Critical']);
                $parts[] = 'priority High or Critical';
            } else {
                $query->where('priority', $priority);
                $parts[] = 'priority '.$priority;
            }
        }

        foreach ([
            'district' => ['district', 'district'],
            'officer' => ['assigned_officer', 'officer'],
            'department' => ['related_department', 'department'],
            'category' => ['category', 'category'],
        ] as $key => [$column, $label]) {
            if (($value = $this->text($filters[$key] ?? null)) !== null) {
                $this->contains($query, $column, $value);
                $parts[] = $label.' contains "'.$value.'"';
            }
        }

        if (($search = $this->text($filters['search'] ?? null)) !== null) {
            $query->where(function (Builder $group) use ($search) {
                foreach (['title', 'summary', 'matter_reference', 'last_action', 'next_action'] as $column) {
                    $group->orWhere(fn (Builder $inner) => $this->contains($inner, $column, $search));
                }
            });
            $parts[] = 'text contains "'.$search.'"';
        }

        if (($reference = $this->reference($filters['reference'] ?? null)) !== null) {
            $query->where('matter_reference', $reference);
            $parts[] = 'reference '.$reference;
        }

        $this->applyDeadline($query, $filters['deadline'] ?? null, $today, $parts);

        return [$query, 'Understood as: '.implode(', ', $parts).'.'];
    }

    private function applyDeadline(Builder $query, mixed $value, string $today, array &$parts): void
    {
        $deadline = $this->text($value);

        if ($deadline === null) {
            return;
        }

        $deadline = strtolower($deadline);

        if (! in_array($deadline, self::DEADLINES, true)) {
            throw new InvalidArgumentException('I did not recognise that deadline filter. I can use overdue, today, the next 7 days, the next 30 days, or no deadline.');
        }

        switch ($deadline) {
            case 'overdue':
                $query->whereIn('status', ['Open', 'Pending'])->whereNotNull('deadline')->where('deadline', '<', $today);
                $parts[] = 'overdue (active matters past their deadline)';
                break;
            case 'today':
                $query->where('deadline', $today);
                $parts[] = 'deadline today';
                break;
            case 'next_7_days':
                $query->whereBetween('deadline', [$today, $this->weekEnd($today)]);
                $parts[] = 'deadline within the next 7 days';
                break;
            case 'next_30_days':
                $query->whereBetween('deadline', [$today, date('Y-m-d', strtotime($today.' +30 days UTC'))]);
                $parts[] = 'deadline within the next 30 days';
                break;
            case 'none':
                $query->whereNull('deadline');
                $parts[] = 'no deadline set';
                break;
            case 'overdue_or_next_7_days':
                $query->whereIn('status', ['Open', 'Pending'])->whereNotNull('deadline')->where('deadline', '<=', $this->weekEnd($today));
                $parts[] = 'overdue or due within 7 days';
                break;
        }
    }

    // -------------------------------------------------------------- documents

    private function listDocuments(array $plan): array
    {
        $filters = $plan['documents'] ?? [];
        $query = Document::query()->with('matter');
        $parts = [];

        if (($category = $this->text($filters['category'] ?? null)) !== null) {
            $match = collect(DocumentController::CATEGORIES)->first(fn ($option) => strcasecmp($option, $category) === 0);

            if ($match === null) {
                throw new InvalidArgumentException('I did not recognise the document category "'.$category.'". Categories: '.implode(', ', DocumentController::CATEGORIES).'.');
            }

            $query->where('category', $match);
            $parts[] = 'category '.$match;
        }

        if (($review = $this->text($filters['review_status'] ?? null)) !== null) {
            $status = match (true) {
                str_contains(strtolower($review), 'review') => 'Needs review',
                str_contains(strtolower($review), 'filed') => 'Filed',
                default => throw new InvalidArgumentException('I can filter documents by Filed or Needs review.'),
            };

            $query->where('review_status', $status);
            $parts[] = 'status '.$status;
        }

        if (($reference = $this->reference($filters['matter_reference'] ?? null)) !== null) {
            $matter = Matter::where('matter_reference', $reference)->first();

            if (! $matter) {
                return $this->reply('No matter with the reference '.$reference.' is in the records available to your account.');
            }

            $query->where('matter_id', $matter->id);
            $parts[] = 'linked to '.$reference;
        }

        if (filter_var($filters['unlinked'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNull('matter_id');
            $parts[] = 'not linked to a matter';
        }

        if (($search = $this->text($filters['search'] ?? null)) !== null) {
            $query->where(function (Builder $group) use ($search) {
                foreach (['original_name', 'extracted_text', 'classification_reason'] as $column) {
                    $group->orWhere(fn (Builder $inner) => $this->contains($inner, $column, $search));
                }
            });
            $parts[] = 'text contains "'.$search.'"';
        }

        $understood = 'Understood as: documents'.($parts === [] ? '' : ', '.implode(', ', $parts)).'.';

        $total = (clone $query)->count();

        if ($total === 0) {
            return $this->reply($understood."\n\nNo documents match.");
        }

        $documents = $query->latest('id')->limit(self::LIMIT)->get();

        $lines = $documents->map(fn (Document $document) => implode(' | ', [
            '- DOC-'.$document->id,
            $document->original_name,
            $document->category,
            $document->review_status,
            $document->matter?->matter_reference ?? 'not linked',
            'scanned '.($document->classified_at?->format('Y-m-d') ?? 'n/a'),
        ]))->implode("\n");

        $count = $total === 1 ? '1 document found.' : $total.' documents found.';

        if ($total > self::LIMIT) {
            $count .= ' Showing the latest '.self::LIMIT.' of '.$total.'.';
        }

        return $this->reply($understood."\n".$count."\n\n".$lines, null, $documents);
    }

    private function documentText(array $plan): array
    {
        $id = $plan['document_id'] ?? null;

        if (! is_numeric($id)) {
            throw new InvalidArgumentException('Please give the document number, for example DOC-5.');
        }

        $document = Document::with('matter')->find((int) $id);

        if (! $document) {
            return $this->reply('No document DOC-'.(int) $id.' is in the records available to your account.');
        }

        $text = 'DOC-'.$document->id.': '.$document->original_name
            ."\nCategory: ".$document->category.'. Status: '.$document->review_status.'. Matter: '.($document->matter?->matter_reference ?? 'not linked').'.'
            ."\n\nScanner summary (written by the AI scanner when the file was uploaded, so open the original file to confirm anything important):\n"
            .(blank($document->extracted_text) ? 'No text was extracted from this file.' : trim((string) $document->extracted_text));

        return $this->reply($text, null, collect([$document]));
    }

    // ----------------------------------------------------------------- helpers

    private function unknown(): array
    {
        return $this->reply(
            "I can't answer that from the records.\n\n"
            ."I can:\n"
            ."- list or count matters by status, section, priority, district, officer, department or deadline\n"
            ."- show one matter by its reference, for example HM-2026-004\n"
            ."- give a briefing of overdue, due soon, high priority and waiting matters\n"
            ."- list documents, or show what the scanner read from one (for example DOC-5)"
        );
    }

    /**
     * @param  Collection<int, Matter>|null  $matters
     * @param  Collection<int, Document>|null  $documents
     */
    private function reply(string $answer, ?Collection $matters = null, ?Collection $documents = null): array
    {
        return [
            'answer' => $answer,
            'references' => ($matters ?? collect())->take(self::LINK_LIMIT)->map(fn (Matter $matter) => [
                'reference' => $matter->matter_reference,
                'title' => $matter->title,
                'url' => route('matters.show', $matter),
            ])->values()->all(),
            'documents' => ($documents ?? collect())->take(self::LINK_LIMIT)->map(fn (Document $document) => [
                'id' => 'DOC-'.$document->id,
                'name' => $document->original_name,
                'url' => route('documents.show', $document),
            ])->values()->all(),
        ];
    }

    private function ordered(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::PRIORITY_ORDER)
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->orderBy('matter_reference');
    }

    private function matterLine(Matter $matter, string $today): string
    {
        return '- '.implode(' | ', [
            $matter->matter_reference,
            $matter->title,
            $matter->section,
            $matter->priority,
            $matter->status,
            $this->dueText($matter, $today),
            'Officer: '.(blank($matter->assigned_officer) ? 'not assigned' : $matter->assigned_officer),
        ]);
    }

    /**
     * The deadline in words. The day count is worked out here, never by an AI.
     */
    private function dueText(Matter $matter, string $today): string
    {
        if (blank($matter->deadline)) {
            return 'no deadline';
        }

        $deadline = substr((string) $matter->deadline, 0, 10);

        if (! in_array($matter->status, ['Open', 'Pending'], true)) {
            return 'deadline '.$deadline;
        }

        $days = DeadlineStage::daysLeft($deadline, $today);

        return match (true) {
            $days < 0 => 'OVERDUE by '.abs($days).' day(s), deadline '.$deadline,
            $days === 0 => 'due TODAY, deadline '.$deadline,
            default => 'due in '.$days.' day(s), deadline '.$deadline,
        };
    }

    private function today(): string
    {
        return DeadlineStage::today(config('reminders.timezone', 'Asia/Karachi'));
    }

    private function weekEnd(string $today): string
    {
        return date('Y-m-d', strtotime($today.' +7 days UTC'));
    }

    private function status(mixed $value): string
    {
        $value = $this->text($value);

        if ($value === null) {
            return 'Active';
        }

        foreach (['Open', 'Pending', 'Closed', 'Active', 'Any'] as $option) {
            if (strcasecmp($value, $option) === 0) {
                return $option;
            }
        }

        throw new InvalidArgumentException('I did not recognise the status "'.$value.'". Use Open, Pending, Closed, Active or Any.');
    }

    private function section(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $known = collect(MatterExtractor::SECTIONS)
            ->merge(Matter::query()->distinct()->pluck('section'))
            ->filter()
            ->unique()
            ->values();

        $match = $known->first(fn ($section) => strcasecmp((string) $section, $value) === 0);

        if ($match === null) {
            throw new InvalidArgumentException('I did not recognise the section "'.$value.'". Sections: '.$known->implode(', ').'.');
        }

        return (string) $match;
    }

    private function priority(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        if (strcasecmp(str_replace([' ', '-'], '_', $value), 'High_or_Critical') === 0) {
            return 'High_or_Critical';
        }

        foreach (self::PRIORITIES as $option) {
            if (strcasecmp($value, $option) === 0) {
                return $option;
            }
        }

        throw new InvalidArgumentException('I did not recognise the priority "'.$value.'". Use Critical, High, Normal or Low.');
    }

    private function reference(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/HM[\s\-_]*(\d{4})[\s\-_]*(\d{3})/i', $value, $hit)) {
            return null;
        }

        return 'HM-'.$hit[1].'-'.$hit[2];
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return mb_substr($value, 0, 100);
    }

    /**
     * Case-insensitive "contains" that treats % and _ in the search text as plain characters (escaped with !).
     * The column name always comes from this class, never from the question.
     */
    private function contains(Builder $query, string $column, string $value): void
    {
        $needle = '%'.preg_replace('/([%_!])/', '!$1', mb_strtolower($value)).'%';

        $query->whereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$needle]);
    }
}
