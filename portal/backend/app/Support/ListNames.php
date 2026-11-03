<?php

namespace App\Support;

use Illuminate\Support\Collection;

/*
Names on the lists staff add to - event categories, services, resource types,
counties and agencies (Burchfield B55/B60, 2026-10-28). One rule for all five,
applied by App\Rules\DistinctListName on each screen's form:

  - Spaces are tidied on save: leading, trailing and doubled spaces go.
  - A new or renamed name is refused when it matches another on the same list
    ignoring capitals, or when every word sounds alike (Soundex, word by
    word), which catches misspellings: "Bradly" for "Bradley", "Counselling"
    for "Counseling". A word's trailing "s" is ignored first, so plurals
    count too: "Support Groups" for "Support Group", "Others" for "Other"
    (decided with Harbor Safe's B55/B60 review). Words with digits ("24/7",
    "District 9") must match exactly, since Soundex ignores digits.

Word by word, because a whole-name Soundex keeps only the first letter and
three digits, and would call "24/7 Crisis Line" a copy of "Crisis Counseling".
Checked against the real lists and the counties around Bradley: no false
matches. Abbreviations ("Dept" for "Department") are not caught; nothing
sound-based could.
*/
final class ListNames
{
    public static function tidy(?string $name): ?string
    {
        return $name === null ? null : trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    // Two names with the same key sound alike.
    public static function key(string $name): string
    {
        $words = preg_split('/\s+/u', mb_strtolower(self::tidy($name) ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(
            fn (string $word): string => preg_match('/[a-z]/', $word) && ! preg_match('/\d/', $word)
                ? soundex(strlen($word) > 3 ? preg_replace('/s$/', '', $word) : $word)
                : $word,
            $words,
        ));
    }

    // The existing name $name would duplicate, or null when it is distinct.
    public static function clashWith(string $name, Collection $existing): ?string
    {
        $tidy = mb_strtolower(self::tidy($name) ?? '');
        $key = self::key($name);

        return $existing->first(fn (?string $other): bool => $other !== null
            && (mb_strtolower(self::tidy($other) ?? '') === $tidy || self::key($other) === $key));
    }
}
