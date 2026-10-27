<?php

namespace App\Filament\Clusters\Analytics\Pages;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Analytics\AnalyticsPage;
use App\Services\Analytics\FollowUpStats;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;
use Filament\Facades\Filament;

// Analytics tab 4: how follow-up is going on the forms submitted in the
// range. Admins and super admins only (they work every kind; a secretary
// works resource requests only).
class FollowUpAnalytics extends AnalyticsPage
{
    protected static ?string $slug = 'follow-ups';

    protected static ?string $title = 'Follow-ups';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    public function description(): string
    {
        return 'How Harbor Safe’s follow-up is going on the assessments and resource requests submitted in this range.';
    }

    protected function sections(DateRange $range): array
    {
        $stats = app(FollowUpStats::class)->summary($range);
        $statuses = FollowUpStatus::cases();

        $tiles = [
            ['label' => 'Still open', 'value' => number_format($stats['open']), 'sub' => FollowUpStatus::NeedsFollowUp->label().' or '.FollowUpStatus::AttemptedContact->label()],
            ['label' => 'Reached', 'value' => Chart::share($stats['reached'], $stats['total']), 'sub' => 'of '.number_format($stats['total']).' '.str('form')->plural($stats['total'])],
            ['label' => 'Typical time to first contact', 'value' => FollowUpStats::formatMinutes($stats['medianMinutes']) ?? '—', 'sub' => 'median, from submission to the first status change'],
            ['label' => 'Reminders needed', 'value' => number_format($stats['remindedOnce']).' · '.number_format($stats['remindedTwice']), 'sub' => 'still waiting at 24 h · at 48 h'],
        ];

        $rows = array_map(fn (FollowUpKind $kind): array => [
            'label' => str($kind->label())->plural()->toString(),
            'values' => [
                ...array_map(fn (FollowUpStatus $status): int => $stats['byKind'][$kind->value][$status->value], $statuses),
                array_sum($stats['byKind'][$kind->value]),
            ],
        ], FollowUpKind::cases());

        $cards = [
            Chart::horizontal('Status now', array_map(fn (FollowUpStatus $status): array => [
                'label' => $status->label(),
                'value' => $stats['byStatus'][$status->value],
            ], $statuses), 'forms'),
            Chart::vertical('Time to first contact', Chart::items($stats['contactBands']), 'forms', [
                'note' => 'From submission to the first status change.',
            ]),
            Chart::table('By form type', [
                ...array_map(fn (FollowUpStatus $status): string => $status->label(), $statuses),
                'Total',
            ], $rows, [
                'firstColumn' => 'Form',
                'wide' => true,
                'footnote' => 'Service feedback is never followed up, so it isn’t counted here.',
            ]),
        ];

        return [['heading' => null, 'tiles' => $tiles, 'cards' => $cards]];
    }
}
