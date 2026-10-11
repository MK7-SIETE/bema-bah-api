<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Typo-tolerant car search, done in plain PHP on top of MySQL
 * (no paid search service needed for a dealership-sized catalogue).
 *
 * How it works:
 *  1. MySQL narrows the cars (available, for sale, filters applied).
 *  2. We read ONLY the searchable columns of those cars.
 *  3. Each car gets a score for the words the customer typed.
 *     Every typed word must match something, or the car is left out.
 *  4. Cars are sorted best score first (ties: newest first).
 */
class CarSearch
{
    // SETTING 1: the columns the search looks at. Add or remove names to change it.
    // (Add 'description' if you want text inside descriptions to be searchable.)
    public const FIELDS = [
        'make', 'model', 'body_type', 'fuel', 'transmission',
        'color', 'year', 'source_country', 'condition',
    ];

    // SETTING 2: how many typos are forgiven, by word length.
    // 4-5 letters = 1 typo, 6+ letters = 2 typos. Shorter words must match exactly.
    private static function tolerance(int $length): int
    {
        return $length <= 5 ? 1 : 2;
    }

    /** Staff only: turn "BB-0012", "bb12" or "BB 12" into 12 (the car's real id). */
    public static function vehicleId(string $term): ?int
    {
        return preg_match('/^\s*BB[\s\-]?0*(\d+)\s*$/i', $term, $m) ? (int) $m[1] : null;
    }

    /** "Toyta  Corolla!" -> ['toyta', 'corolla'] */
    public static function tokens(string $term): array
    {
        $parts = preg_split('/[^a-z0-9]+/', strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($parts, 0, 6); // ignore anything past 6 words
    }

    /** Score one car (its field values) against the typed words. 0 = no match. */
    public static function score(array $tokens, array $values): int
    {
        $values   = array_map(fn ($v) => strtolower(trim((string) $v)), $values);
        $squashed = array_map(fn ($v) => preg_replace('/[^a-z0-9]/', '', $v), $values);

        $words = [];
        foreach ($values as $v) {
            foreach (preg_split('/[^a-z0-9]+/', $v, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                $words[] = $w;
            }
        }

        $total = 0;
        foreach ($tokens as $token) {
            $s = self::tokenScore($token, $squashed, $words);
            if ($s === 0) {
                return 0; // one typed word matched nothing, so this car is out
            }
            $total += $s;
        }

        return $total;
    }

    private static function tokenScore(string $token, array $squashed, array $words): int
    {
        // 100: the typed text appears inside a field ("toy" in "toyota",
        //      "landcruiser" in "Land Cruiser", "2015" in the year).
        foreach ($squashed as $field) {
            if ($field !== '' && str_contains($field, $token)) {
                return 100;
            }
        }

        $len = strlen($token);
        if ($len < 4 || ctype_digit($token)) {
            return 0; // too short, or a number: no guessing, exact match only
        }

        $tolerance = self::tolerance($len);
        $best      = 0;

        foreach ($words as $word) {
            // distance to the whole word, and to the start of the word
            // (so a half-typed word with a typo still matches: "toyt" -> "toyota")
            $d = min(levenshtein($token, $word), levenshtein($token, substr($word, 0, $len)));

            if ($d <= $tolerance) {
                $best = max($best, 80 - $d * 10);          // 80 / 70 / 60
            } elseif (($m = metaphone($token)) !== '' && $m === metaphone($word)) {
                $best = max($best, 50);                    // sounds alike ("lexuz")
            }
        }

        return $best;
    }

    /** Car ids that match, best match first. */
    public static function rankedIds(Builder $query, string $term): array
    {
        $tokens = self::tokens($term);
        if (! $tokens) {
            return [];
        }

        $scores = [];
        $rows   = $query->orderByDesc('id')->get(array_merge(['id'], self::FIELDS));

        foreach ($rows as $car) {
            $score = self::score($tokens, array_map(fn ($f) => $car->{$f}, self::FIELDS));
            if ($score > 0) {
                $scores[$car->id] = $score;
            }
        }

        arsort($scores); // stable in PHP 8: equal scores keep newest-first
        return array_keys($scores);
    }
}