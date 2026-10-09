<?php

namespace App\Services;

use App\Models\Matter;
use Illuminate\Support\Str;

class MatterMatcher
{
    private const IGNORED_WORDS = [
        'review', 'matter', 'follow', 'with', 'from', 'that', 'this', 'and', 'the', 'for',
    ];

    /**
     * Find a matter by an exact reference number (HM-YYYY-NNN) in any of the given texts.
     */
    public function findByReference(?string ...$sources): ?Matter
    {
        foreach ($sources as $text) {
            if (blank($text)) {
                continue;
            }

            if (! preg_match_all('/HM[\s\-_]*(\d{4})[\s\-_]*(\d{3})/i', $text, $hits, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($hits as $hit) {
                $matter = Matter::where('matter_reference', 'HM-'.$hit[1].'-'.$hit[2])->first();

                if ($matter) {
                    return $matter;
                }
            }
        }

        return null;
    }

    /**
     * Suggest an active matter when all (or nearly all) of its title keywords appear in the text.
     * Returns null when nothing matches or when two matters match equally well.
     */
    public function suggestByTitle(?string $text): ?Matter
    {
        if (blank($text)) {
            return null;
        }

        $haystack = Str::lower($text);
        $best = null;
        $bestScore = 0.0;
        $tied = false;

        foreach (Matter::whereIn('status', ['Open', 'Pending'])->get() as $matter) {
            $words = collect(preg_split('/[^a-z0-9]+/', Str::lower($matter->title), -1, PREG_SPLIT_NO_EMPTY))
                ->reject(fn ($word) => strlen($word) < 4 || in_array($word, self::IGNORED_WORDS, true))
                ->unique()
                ->values();

            if ($words->count() < 2) {
                continue;
            }

            $score = $words->filter(fn ($word) => str_contains($haystack, $word))->count() / $words->count();

            if ($score < 0.75) {
                continue;
            }

            if ($score > $bestScore) {
                $best = $matter;
                $bestScore = $score;
                $tied = false;
            } elseif ($score === $bestScore) {
                $tied = true;
            }
        }

        return $tied ? null : $best;
    }
}
