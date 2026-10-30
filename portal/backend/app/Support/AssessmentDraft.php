<?php

namespace App\Support;

use App\Models\User;

/*
An unfinished officer assessment kept through the idle logout (roadmap
section 0, item 11). Settled 2026-10-24 with Harbor Safe's law-enforcement
contact: the form never reaches the server until Submit. It stays in the
browser tab that was filling it in, encrypted:

- saved to the tab's sessionStorage (never localStorage), so it is gone when
  the tab closes and no other tab or window sees it;
- encrypted with AES-GCM in the browser, under a key that is this person's
  alone (keyFor()) and is only ever written into the New assessment page they
  are signed in to - never stored in the browser, so once signed out the
  saved form can't be read;
- tied to the user id and discarded for anyone else, kept for LIFETIME_MINUTES
  and wiped on Submit, Discard and Sign out (resources/views/filament/
  partials/assessment-draft.blade.php and assessment-draft-guard.blade.php).

The same for officers and police admins: both use the one wizard, each with
their own key. A police admin can't finish an officer's form (it never leaves
the officer's device); they fix a submitted one through Edit instead.

Nothing here is trusted on the way back in: restore() keeps only the wizard's
own fields, and Submit validates everything again.
*/
final class AssessmentDraft
{
    // Neutral on purpose: the guard that reads it is on every page, including
    // a secretary's, who never sees the word "assessment" (FollowUpNewRequestTest).
    public const STORAGE_KEY = 'hs-lap-draft';

    public const LIFETIME_MINUTES = 120;

    // The wizard's fields a saved form may carry. Not SafePhoneConfirmed:
    // a resumed form asks for that confirmation again.
    public const TEXT_FIELDS = [
        'VictimFirstName',
        'VictimLastName',
        'VictimDOB',
        'VictimSex',
        'VictimSafePhoneNumber',
        'OffenderFirstName',
        'OffenderLastName',
        'OffenderDOB',
        'OffenderSex',
        'OffenderVictimRelationship',
        'OffenderVictimRelationshipOther',
    ];

    public const QUESTION_COUNT = 11;

    private const MAX_TEXT_LENGTH = 255;

    // 32 bytes, as hex: an AES-256 key derived from the app key and the user
    // id, so it is the same each time this person signs in (a form saved
    // before the idle logout opens after it) and different for everyone else.
    // Hex rather than base64 so it reads the same in the page however
    // Livewire escapes the script it ships.
    public static function keyFor(User $user): string
    {
        return hash_hmac('sha256', 'hs-assessment-draft|'.$user->getKey(), (string) config('app.key'));
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return [
            ...self::TEXT_FIELDS,
            ...array_map(fn (int $id): string => "RiskIndicator{$id}", range(1, self::QUESTION_COUNT)),
        ];
    }

    /*
    The saved fields, cleaned: only the wizard's own, text as short strings,
    answers as true/false, anything else dropped.

    @return array<string, string|bool|null>
    */
    public static function cleanData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $clean = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $clean[$field] = mb_substr($value, 0, self::MAX_TEXT_LENGTH);
            }
        }

        for ($id = 1; $id <= self::QUESTION_COUNT; $id++) {
            $value = $data["RiskIndicator{$id}"] ?? null;

            if (is_bool($value)) {
                $clean["RiskIndicator{$id}"] = $value;
            }
        }

        return $clean;
    }

    // Every question answered: only then may a resumed form reopen on the review.
    public static function allAnswered(array $data): bool
    {
        for ($id = 1; $id <= self::QUESTION_COUNT; $id++) {
            if (! is_bool($data["RiskIndicator{$id}"] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
