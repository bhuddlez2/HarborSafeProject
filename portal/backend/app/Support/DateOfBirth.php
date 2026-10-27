<?php

namespace App\Support;

/*
The one rule for a date of birth (victim and offender, on both assessments):
no later than today and no earlier than MAX_AGE_YEARS ago. 125 is longer than
anyone lives, on purpose - it leaves room for edge cases while still refusing
typos such as 0198 or 1825.

"Today" is the Eastern day (Timezones::DISPLAY), the day the person entering
it is living in; the server's own day is UTC and runs ahead in the evening.

Used by the officer wizard and Edit pop-up (App\Filament\Forms\DateOfBirthPicker)
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
}
