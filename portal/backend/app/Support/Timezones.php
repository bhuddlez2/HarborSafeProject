<?php

namespace App\Support;

/*
The project's one rule about time: STORE UTC, SHOW EASTERN.

  - The app runs in UTC (config/app.php), and every database connection is
    pinned to UTC too (`timezone` => '+00:00' in config/database.php). So
    Laravel's now() and MariaDB's NOW() / a column's useCurrent() agree, and a
    timestamp means the same moment whichever developer's machine wrote it.
    Before that pin, MariaDB used each machine's own time zone, so the same
    moment was stored differently in Eastern and in other zones.
  - Anything shown to a person is converted to DISPLAY: ->timezone(...) on a
    Filament dateTime column/entry or DateTimePicker, ->setTimezone(...) on a
    Carbon formatted by hand (ChangeHistory, the assessment-record view).

NOT for date-only values (dates of birth, a newsletter's issue date). Those
are calendar dates, not moments, and converting midnight UTC to Eastern would
show the day before. Filament's ->date() converts too, so leave it without a
timezone.

Date filters are the one other place it matters: "Submitted from 10 March"
means from midnight Eastern, so AssessmentFilters::dateRange() turns the
picked day into a UTC range with dayStartUtc()/dayEndUtc().
*/
final class Timezones
{
    public const DISPLAY = 'America/New_York';

    // The first instant of $date (Y-m-d) in the display time zone, in UTC.
    public static function dayStartUtc(string $date): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($date, self::DISPLAY)->startOfDay()->utc();
    }

    // The last instant of $date (Y-m-d) in the display time zone, in UTC.
    public static function dayEndUtc(string $date): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($date, self::DISPLAY)->endOfDay()->utc();
    }
}
