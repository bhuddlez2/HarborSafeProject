<?php

namespace App\Support\Analytics;

use App\Support\Timezones;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/*
The days the Analytics page counts (team plan task 28): a preset or two picked
dates, always whole days in Eastern (Timezones::DISPLAY), turned into a UTC
range for the queries, since every timestamp is stored in UTC.

buckets() splits the range for the "per week / per month" charts:
  - up to 45 days: weeks of 7 days counted back from the last day, so the
    newest week is always whole and only the oldest may be short;
  - up to two years: calendar months (the first and last may be partial);
  - longer: calendar years.
Each bucket carries its own UTC start and end, so a count by bucket is a
CASE over real boundaries and stays right across daylight saving changes
(MariaDB's CONVERT_TZ would need time zone tables a host may not have).
*/
final class DateRange
{
    public const PRESETS = [
        '30d' => 'Last 30 days',
        '3m' => 'Last 3 months',
        '12m' => 'Last 12 months',
    ];

    public const CUSTOM = 'custom';

    public const DEFAULT = '12m';

    private function __construct(
        public readonly string $key,
        // Calendar days in Eastern, Y-m-d.
        public readonly string $fromDate,
        public readonly string $toDate,
    ) {}

    // $today is "today in Eastern" (Y-m-d); tests pass one, the page doesn't.
    public static function preset(string $key, ?string $today = null): self
    {
        $end = Carbon::parse($today ?? Carbon::now(Timezones::DISPLAY)->toDateString());

        $start = match ($key) {
            '30d' => $end->copy()->subDays(29),
            // This month and the two before it, from the 1st.
            '3m' => $end->copy()->startOfMonth()->subMonthsNoOverflow(2),
            // This month and the eleven before it, from the 1st.
            '12m' => $end->copy()->startOfMonth()->subMonthsNoOverflow(11),
            default => throw new InvalidArgumentException("Unknown date range [{$key}]."),
        };

        return new self($key, $start->toDateString(), $end->toDateString());
    }

    // Both dates Y-m-d, from on or before to (the page validates that first).
    public static function custom(string $from, string $to): self
    {
        if ($from > $to) {
            throw new InvalidArgumentException('The start date must be on or before the end date.');
        }

        return new self(self::CUSTOM, $from, $to);
    }

    public function startUtc(): Carbon
    {
        return Timezones::dayStartUtc($this->fromDate);
    }

    public function endUtc(): Carbon
    {
        return Timezones::dayEndUtc($this->toDate);
    }

    // For queries: [start, end] as UTC strings, inclusive.
    public function between(): array
    {
        return [$this->startUtc()->toDateTimeString(), $this->endUtc()->toDateTimeString()];
    }

    // "Nov 1, 2025 – Oct 21, 2026"
    public function label(): string
    {
        $from = Carbon::parse($this->fromDate);
        $to = Carbon::parse($this->toDate);

        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        return $from->year === $to->year
            ? $from->format('M j').' – '.$to->format('M j, Y')
            : $from->format('M j, Y').' – '.$to->format('M j, Y');
    }

    // 'week', 'month' or 'year': what buckets() splits the range into.
    public function unit(): string
    {
        $days = Carbon::parse($this->fromDate)->diffInDays(Carbon::parse($this->toDate)) + 1;

        return match (true) {
            $days <= 45 => 'week',
            $days <= 731 => 'month',
            default => 'year',
        };
    }

    /*
    The chart buckets, oldest first:
      ['label' => axis text, 'tip' => full name, 'start' => UTC, 'end' => UTC]
    */
    public function buckets(): array
    {
        $from = Carbon::parse($this->fromDate);
        $to = Carbon::parse($this->toDate);
        $days = [];

        if ($this->unit() === 'week') {
            for ($end = $to->copy(); $end->gte($from); $end->subDays(7)) {
                $start = $end->copy()->subDays(6)->max($from);
                $days[] = [$start->copy(), $end->copy(), $this->weekLabel($start, $end), $this->weekLabel($start, $end)];
            }

            $days = array_reverse($days);
        } else {
            $isMonth = $this->unit() === 'month';
            $spansYears = $from->year !== $to->year;

            for ($cursor = $isMonth ? $from->copy()->startOfMonth() : $from->copy()->startOfYear(); $cursor->lte($to); $isMonth ? $cursor->addMonthNoOverflow() : $cursor->addYear()) {
                $start = $cursor->copy()->max($from);
                $end = ($isMonth ? $cursor->copy()->endOfMonth() : $cursor->copy()->endOfYear())->startOfDay()->min($to);

                $days[] = $isMonth
                    ? [$start, $end, $spansYears ? $cursor->format("M 'y") : $cursor->format('M'), $cursor->format('F Y')]
                    : [$start, $end, $cursor->format('Y'), $cursor->format('Y')];
            }
        }

        return array_map(fn (array $day): array => [
            'label' => $day[2],
            'tip' => $day[3],
            'start' => Timezones::dayStartUtc($day[0]->toDateString())->toDateTimeString(),
            'end' => Timezones::dayEndUtc($day[1]->toDateString())->toDateTimeString(),
        ], $days);
    }

    private function weekLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        return $start->month === $end->month
            ? $start->format('M j').'–'.$end->format('j')
            : $start->format('M j').'–'.$end->format('M j');
    }
}
