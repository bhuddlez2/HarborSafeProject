<?php

namespace App\Support\Analytics;

use Illuminate\Database\Query\Builder;

/*
What one Analytics card shows, ready for resources/views/filament/analytics/
card.blade.php. Bars are drawn by CSS from `pct`, so there is no chart library
and nothing to load. Every card also gets a one-sentence `summary` holding all
of its numbers, which is what a screen reader hears for the chart.

Items are ['label' => string, 'value' => int|float, 'display' => ?string].
*/
final class Chart
{
    // Bars across: a label, the bar and its number on each row.
    public static function horizontal(string $title, array $items, string $noun, array $options = []): array
    {
        return self::card('horizontal', $title, $items, $noun, $options);
    }

    // Bars up: one column per item, labels underneath.
    public static function vertical(string $title, array $items, string $noun, array $options = []): array
    {
        return self::card('vertical', $title, $items, $noun, $options);
    }

    // A small table: $columns are the headings after the first, and each row
    // is ['label' => string, 'values' => list of numbers, one per column].
    public static function table(string $title, array $columns, array $rows, array $options = []): array
    {
        return [
            'type' => 'table',
            'title' => $title,
            'note' => $options['note'] ?? null,
            'wide' => $options['wide'] ?? false,
            'footnote' => $options['footnote'] ?? null,
            'firstColumn' => $options['firstColumn'] ?? '',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /*
    Counts a query's rows per bucket of $range (DateRange::buckets()), with
    $column the UTC timestamp to sort by. One query: a CASE over each bucket's
    own boundaries. Returns chart items, oldest first, zeros included.
    */
    public static function perPeriod(Builder $query, string $column, DateRange $range): array
    {
        $buckets = $range->buckets();
        $cases = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $cases[] = "WHEN {$column} BETWEEN ? AND ? THEN {$index}";
            array_push($bindings, $bucket['start'], $bucket['end']);
        }

        $counts = (clone $query)
            ->selectRaw('CASE '.implode(' ', $cases).' END AS bucket, COUNT(*) AS n', $bindings)
            ->groupBy('bucket')
            ->pluck('n', 'bucket');

        return array_map(fn (int $index, array $bucket): array => [
            'label' => $bucket['label'],
            'tip' => $bucket['tip'],
            'value' => (int) ($counts[$index] ?? 0),
        ], array_keys($buckets), $buckets);
    }

    // The title for a perPeriod() chart: "Submissions per month", and so on.
    public static function perPeriodTitle(string $what, DateRange $range): string
    {
        return "{$what} per {$range->unit()}";
    }

    // [label => n] as chart items.
    public static function items(array $counts): array
    {
        return array_map(
            fn (string | int $label, int | float | null $value): array => ['label' => (string) $label, 'value' => $value ?? 0],
            array_keys($counts),
            $counts,
        );
    }

    // "12 (40%)": a count and its share of the total, for a tile.
    public static function share(int $count, int $total): string
    {
        return number_format($count).' ('.($total > 0 ? round($count / $total * 100) : 0).'%)';
    }

    private static function card(string $type, string $title, array $items, string $noun, array $options): array
    {
        $max = $options['max'] ?? max([1, ...array_map(fn (array $item): float => (float) $item['value'], $items)]);

        $bars = array_map(function (array $item) use ($max, $noun, $type): array {
            $display = $item['display'] ?? number_format((float) $item['value']);
            $value = (float) $item['value'];

            return [
                'label' => $item['label'],
                // For the export (AnalyticsExport): the number itself, and the
                // full name where the axis label is shortened (vertical bars:
                // "Oct" is "October 2026", "3" is "3 yes").
                'value' => $value,
                'name' => $type === 'vertical' ? ($item['tip'] ?? $item['label']) : $item['label'],
                'display' => $display,
                // At least a sliver for any non-zero value, so it doesn't read as none.
                'pct' => $value > 0 ? max(1, (int) round($value / $max * 100)) : 0,
                'tip' => ($item['tip'] ?? $item['label']).': '.$display.($noun !== '' ? " {$noun}" : ''),
            ];
        }, $items);

        return [
            'type' => $type,
            'title' => $title,
            'noun' => $noun,
            'note' => $options['note'] ?? null,
            'wide' => $options['wide'] ?? false,
            'footnote' => $options['footnote'] ?? null,
            // Numbers above columns get crowded past this many.
            'showValues' => $type === 'horizontal' || count($bars) <= 16,
            'bars' => $bars,
            'summary' => $title.'. '.implode('; ', array_map(fn (array $bar): string => $bar['tip'], $bars)).'.',
        ];
    }
}
