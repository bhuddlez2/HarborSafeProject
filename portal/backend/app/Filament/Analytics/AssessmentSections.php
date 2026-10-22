<?php

namespace App\Filament\Analytics;

use App\Services\Analytics\AssessmentStats;
use App\Filament\Pages\NewAssessment;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;

/*
The tiles and cards of Analytics' two assessment tabs, from
AssessmentStats::officer() or ::civilian(). The officer tab adds Edited and
By agency; the civilian one adds Left contact details. Notes under a card's
title only where the title alone doesn't say it (AS, 2026-10-21).
*/
final class AssessmentSections
{
    // Short names for the eleven questions, for the chart's rows. The full
    // wording is NewAssessment::QUESTIONS.
    public const QUESTION_LABELS = [
        1 => 'Weapon used or threatened',
        2 => 'Threatened to kill',
        3 => 'Tried to choke',
        4 => 'Has or can get a gun',
        5 => 'Active protective order',
        6 => 'Sex offender',
        7 => 'Injuries from past incidents',
        8 => 'Serial abuser',
        9 => 'Gang or criminal ties',
        10 => 'Human trafficking',
        11 => 'Children at risk',
    ];

    public static function build(array $stats, DateRange $range, bool $isOfficer): array
    {
        $total = $stats['total'];

        $tiles = [
            ['label' => 'Assessments submitted', 'value' => number_format($total), 'sub' => $range->label()],
            ['label' => 'Average yes answers', 'value' => $stats['averageYes'] === null ? '—' : number_format($stats['averageYes'], 1), 'sub' => 'out of 11 questions'],
            ['label' => 'High risk', 'value' => Chart::share($stats['highRisk'], $total), 'sub' => AssessmentStats::HIGH_RISK_RULE],
            $isOfficer
                ? ['label' => 'Edited after submission', 'value' => number_format($stats['edited']), 'sub' => null]
                : ['label' => 'Left contact details', 'value' => Chart::share($stats['withContact'], $total), 'sub' => number_format($total - $stats['withContact']).' anonymous'],
        ];

        $perQuestion = array_map(fn (int $i): array => [
            'label' => "Q{$i} · ".self::QUESTION_LABELS[$i],
            'tip' => "Q{$i}: ".NewAssessment::QUESTIONS[$i],
            'value' => $stats['perQuestion'][$i],
            'display' => number_format($stats['perQuestion'][$i]).' · '.($total > 0 ? round($stats['perQuestion'][$i] / $total * 100) : 0).'%',
        ], array_keys($stats['perQuestion']));

        $cards = [
            Chart::vertical(Chart::perPeriodTitle('Submissions', $range), $stats['periods'], 'assessments', [
                'note' => number_format($total).' '.str('assessment')->plural($total).' in this range',
                'wide' => true,
            ]),
            Chart::horizontal('Yes answers per question', $perQuestion, 'answered yes', ['max' => max(1, $total)]),
            Chart::vertical('Yes answers per assessment', array_map(fn (int $yes, int $n): array => [
                'label' => (string) $yes,
                'tip' => "{$yes} yes",
                'value' => $n,
            ], array_keys($stats['yesSpread']), $stats['yesSpread']), 'assessments', [
                'note' => 'How many of the 11 questions each assessment answered yes to.',
            ]),
        ];

        if ($isOfficer) {
            $cards[] = Chart::horizontal('By agency', Chart::items($stats['agencies']), 'assessments', [
                'note' => 'Counted by each officer’s current agency.',
            ]);
        }

        $cards[] = Chart::horizontal('Relationship to victim', Chart::items($stats['relationships']), 'assessments', ['wide' => ! $isOfficer]);
        $cards[] = Chart::horizontal('Victim sex', Chart::items($stats['victimSex']), 'assessments');
        $cards[] = Chart::horizontal('Offender sex', Chart::items($stats['offenderSex']), 'assessments');
        $cards[] = Chart::horizontal('Victim age', Chart::items($stats['victimAge']), 'assessments', [
            'note' => 'Age on the day the assessment was submitted.',
        ]);
        $cards[] = Chart::horizontal('Offender age', Chart::items($stats['offenderAge']), 'assessments', [
            'note' => 'Age on the day the assessment was submitted.',
        ]);

        return [['heading' => null, 'tiles' => $tiles, 'cards' => $cards]];
    }
}
