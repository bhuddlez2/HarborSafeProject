<?php

namespace App\Filament\Forms;

use App\Support\Timezones;
use Closure;
use Illuminate\Support\Carbon;

/*
A realistic range for a date a person types: the picked day (Eastern) must lie
between two Eastern days, inclusive.

Used where a record can be edited later (events, newsletters): a value equal to
what is already saved always passes ($isSaved), so an old record can still be
edited without moving its date - only a new or changed date is checked. Pair
it with ->minDate()/->maxDate() on create only, which grey out the calendar's
other days (on edit they would refuse the saved date too).

Use as ->rule(fn (?Model $record): Closure => DateWindow::rule(...)).
*/
final class DateWindow
{
    /**
     * @param  Closure(): string  $earliest  Y-m-d, Eastern
     * @param  Closure(): string  $latest  Y-m-d, Eastern
     * @param  (Closure(Carbon): bool)|null  $isSaved  the picked value, Eastern
     */
    public static function rule(Closure $earliest, Closure $latest, string $tooEarly, string $tooLate, ?Closure $isSaved = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($earliest, $latest, $tooEarly, $tooLate, $isSaved): void {
            if (blank($value) || ! is_string($value)) {
                return;
            }

            try {
                // The picker's value is Eastern wall-clock time.
                $picked = Carbon::parse($value, Timezones::DISPLAY);
            } catch (\Throwable) {
                $fail('Enter a valid date.');

                return;
            }

            if ($isSaved !== null && $isSaved($picked)) {
                return;
            }

            $day = $picked->toDateString();

            if ($day < $earliest()) {
                $fail($tooEarly);
            } elseif ($day > $latest()) {
                $fail($tooLate);
            }
        };
    }
}
