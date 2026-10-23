<?php

namespace App\Filament\Clusters\Analytics\Pages;

use App\Enums\UserRole;
use App\Filament\Analytics\AnalyticsPage;
use App\Services\Analytics\FeedbackStats;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;
use Filament\Facades\Filament;

// Analytics tab 3: the website's service feedback and resource request forms,
// plus the ones staff entered. The one tab secretaries see (they already
// read both forms in Submissions).
class FeedbackAnalytics extends AnalyticsPage
{
    protected static ?string $slug = 'feedback-and-requests';

    protected static ?string $title = 'Feedback and resource requests';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::SuperAdmin,
            UserRole::Secretary,
        );
    }

    public function description(): string
    {
        return 'Totals for the website’s feedback and resource request forms, plus forms entered by staff.';
    }

    protected function sections(DateRange $range): array
    {
        $stats = app(FeedbackStats::class);
        $feedback = $stats->serviceFeedback($range);
        $requests = $stats->resourceRequests($range);

        $total = $feedback['total'];
        $online = $total - $feedback['staff'];

        $feedbackSection = [
            'heading' => 'Service feedback',
            'tiles' => [
                ['label' => 'Feedback forms received', 'value' => number_format($total), 'sub' => $range->label()],
                ['label' => 'Average rating', 'value' => $feedback['averageRating'] === null ? '—' : number_format($feedback['averageRating'], 1).' / 5', 'sub' => 'across every service'],
                ['label' => 'Entered by staff', 'value' => Chart::share($feedback['staff'], $total), 'sub' => number_format($online).' from the website'],
            ],
            'cards' => [
                Chart::vertical(Chart::perPeriodTitle('Feedback', $range), $feedback['periods'], 'forms', [
                    'note' => number_format($total).' '.str('form')->plural($total).' in this range',
                    'wide' => true,
                ]),
                Chart::horizontal('Average rating per service', array_map(fn (string $name, ?float $average): array => [
                    'label' => $name,
                    'value' => $average ?? 0,
                    'display' => $average === null ? '—' : number_format($average, 1).' / 5',
                ], array_keys($feedback['averagePerService']), $feedback['averagePerService']), 'average', [
                    'note' => 'Out of 5.',
                    'max' => 5,
                ]),
                Chart::horizontal('Forms per service', Chart::items($feedback['perService']), 'forms'),
                Chart::vertical('Ratings given', array_map(fn (int $rating, int $n): array => [
                    'label' => (string) $rating,
                    'tip' => "Rating {$rating}",
                    'value' => $n,
                ], array_keys($feedback['ratings']), $feedback['ratings']), 'forms', [
                    'note' => 'From 1 (lowest) to 5 (highest).',
                ]),
                Chart::horizontal('Where forms came from', [
                    ['label' => 'Website', 'value' => $online],
                    ['label' => 'Entered by staff', 'value' => $feedback['staff']],
                ], 'forms'),
            ],
        ];

        $requestsSection = [
            'heading' => 'Resource requests',
            'tiles' => [
                ['label' => 'Resource requests received', 'value' => number_format($requests['total']), 'sub' => $range->label()],
                ['label' => 'Started by staff', 'value' => number_format($requests['staff']), 'sub' => 'from a follow-up page'],
                ['label' => 'Counties', 'value' => number_format($requests['countiesReached']), 'sub' => 'with at least one request'],
            ],
            'cards' => [
                Chart::vertical(Chart::perPeriodTitle('Requests', $range), $requests['periods'], 'requests', [
                    'note' => number_format($requests['total']).' '.str('request')->plural($requests['total']).' in this range',
                    'wide' => true,
                ]),
                Chart::horizontal('By county', Chart::items($requests['perCounty']), 'requests'),
                Chart::horizontal('By resource type', Chart::items($requests['perType']), 'requests', [
                    'footnote' => 'A request can name several types, so these can add up to more than the total.',
                ]),
            ],
        ];

        return [$feedbackSection, $requestsSection];
    }
}
