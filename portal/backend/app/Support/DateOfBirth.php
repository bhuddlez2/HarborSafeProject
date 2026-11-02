<?php

namespace App\Support;

/*
The one rule for a date of birth (victim and offender, on both assessments):
no later than today and no earlier than MAX_AGE_YEARS ago. 125 is longer than
anyone lives, on purpose - it leaves room for edge cases while still refusing
typos such as 0198 or 1825.

"Today" is the Eastern day (Timezones::DISPLAY), the day the person entering
it is living in; the server's own day is UTC and runs ahead in the evening.

Used by the officer wizard and Edit pop-up (App\Filament\Forms\DateOfBirthInput)
and the civilian endpoint (CivilianAssessmentStoreRequest). The website
mirrors it in website/frontend/src/app/lib/validators.js (dateOfBirthError);
change both together.
*/
final class DateOfBirth
{
    public const MAX_AGE_YEARS = 125;

    public const FUTURE_MESSAGE = "Date of birth can't be in the future.";

    public const TOO_OLD_MESSAGE = "Date of birth can't be more than 125 years ago.";

    public static function latest(): string
    {
        return Timezones::today();
    }

    // NoOverflow: on 29 February the earliest day is 28 February, not
    // 1 March, in a year that has no 29th.
    public static function earliest(): string
    {
        return now(Timezones::DISPLAY)->subYearsNoOverflow(self::MAX_AGE_YEARS)->toDateString();
    }

    // For a Laravel validator; add 'required' or 'nullable' as the field needs.
    public static function rules(): array
    {
        return [
            'date_format:Y-m-d',
            'after_or_equal:'.self::earliest(),
            'before_or_equal:'.self::latest(),
        ];
    }

    /*
    The panel's typed box: MM/DD/YYYY, the
    format the website's civilian form takes too (formatDateInput in
    validators.js). The box holds the typed text; it is checked in that format
    and saved as Y-m-d (App\Filament\Forms\DateOfBirthInput).
    */
    public const TYPED_FORMAT = 'm/d/Y';

    public const MASK = '99/99/9999';

    public const TYPED_MESSAGE = 'Enter a real date as MM/DD/YYYY.';

    /*
    rules() for the typed box. The limits are written in the typed format too,
    since Laravel reads them with the field's own date_format. date_format also
    refuses a day that doesn't exist (02/30), as PHP would otherwise roll it on
    to 1 March.
    */
    public static function typedRules(): array
    {
        return [
            'date_format:'.self::TYPED_FORMAT,
            'after_or_equal:'.self::toTyped(self::earliest()),
            'before_or_equal:'.self::toTyped(self::latest()),
        ];
    }

    // '1985-03-14' -> '03/14/1985'; anything else (already typed, empty) unchanged.
    public static function toTyped(?string $value): ?string
    {
        if (filled($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts)) {
            return "{$parts[2]}/{$parts[3]}/{$parts[1]}";
        }

        return $value;
    }

    // '03/14/1985' -> '1985-03-14'; empty becomes null. Anything else unchanged,
    // which typedRules() has refused before it gets this far.
    public static function toIso(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $parts)) {
            return "{$parts[3]}-{$parts[1]}-{$parts[2]}";
        }

        return $value;
    }
}
