<?php

namespace App\Services\Analytics;

use App\Enums\OffenderRelationship;
use App\Models\Agency;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/*
The numbers behind Analytics' two assessment tabs (team plan task 28): officer
assessments (law_enforcement_assessment) and civilian ones
(_private_assessment), both joined to their eleven answers. Totals only:
nothing here selects a name, phone number or date of birth into PHP.

Counted in SQL, a handful of grouped queries per tab, on the full-access Portal
connection (the page is for Harbor Safe staff, who may open every record
anyway). Every query narrows to the date range on DateCreated and, when the
page's filter is set, to the victim's sex and optionally the offender's.

High risk is decided here, once (HIGH_RISK_RULE): a yes to any of Q1-3
(weapon, threat to kill, choking), or 4 or more yes in all. Agreed with AS on
2026-10-21; Harbor Safe to confirm. The assessment tables' red badge still
uses 4+ only (AssessmentColumns).
*/
final class AssessmentStats
{
    public const QUESTION_COUNT = 11;

    public const HIGH_RISK_RULE = 'Yes to Q1–3, or 4 or more yes';

    // Stored codes (NewAssessment::sexSelect(), CivilianAssessmentStoreRequest::SEXES).
    public const SEXES = ['F' => 'Female', 'M' => 'Male', 'O' => 'Other'];

    // [label, lowest age, highest age], age on the day of submission.
    private const AGE_BANDS = [
        ['Under 18', null, 17],
        ['18–24', 18, 24],
        ['25–34', 25, 34],
        ['35–44', 35, 44],
        ['45–54', 45, 54],
        ['55 and over', 55, null],
    ];

    public function officer(DateRange $range, ?string $victimSex = null, ?string $offenderSex = null): array
    {
        $base = $this->base('law_enforcement_assessment', $range, $victimSex, $offenderSex);

        return [
            ...$this->common($base, $range),
            'edited' => (clone $base)
                ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('assessment_edits as e')->whereColumn('e.DocumentID', 'a.DocumentID'))
                ->count(),
            'agencies' => $this->agencies($base),
        ];
    }

    public function civilian(DateRange $range, ?string $victimSex = null, ?string $offenderSex = null): array
    {
        $base = $this->base('_private_assessment', $range, $victimSex, $offenderSex);

        return [
            ...$this->common($base, $range),
            // Contact details are kept only when someone reports for another
            // person and declines anonymity; otherwise SubmissionID is null.
            'withContact' => (clone $base)->whereNotNull('a.SubmissionID')->count(),
        ];
    }

    // The sum of the eleven answers, as SQL on the answers alias `x`.
    public static function yesSql(): string
    {
        return '('.collect(range(1, self::QUESTION_COUNT))
            ->map(fn (int $i): string => "COALESCE(x.RiskIndicator{$i}, 0)")
            ->implode(' + ').')';
    }

    // HIGH_RISK_RULE as SQL on the answers alias `x`.
    public static function highRiskSql(): string
    {
        return '(x.RiskIndicator1 = 1 OR x.RiskIndicator2 = 1 OR x.RiskIndicator3 = 1 OR '.self::yesSql().' >= 4)';
    }

    private function base(string $table, DateRange $range, ?string $victimSex, ?string $offenderSex): Builder
    {
        return DB::connection('Portal')->table("{$table} as a")
            ->join('_assessment_answers as x', 'x.AssessmentDocID', '=', 'a.AssessmentDocID')
            ->whereBetween('a.DateCreated', $range->between())
            ->when($victimSex !== null, fn (Builder $q) => $q->where('a.VictimSex', $victimSex))
            ->when($offenderSex !== null, fn (Builder $q) => $q->where('a.OffenderSex', $offenderSex));
    }

    private function common(Builder $base, DateRange $range): array
    {
        $yes = self::yesSql();
        $perQuestion = collect(range(1, self::QUESTION_COUNT))
            ->map(fn (int $i): string => "SUM(COALESCE(x.RiskIndicator{$i}, 0)) AS q{$i}")
            ->implode(', ');

        $totals = (clone $base)
            ->selectRaw("COUNT(*) AS total, AVG({$yes}) AS average_yes, SUM(CASE WHEN ".self::highRiskSql()." THEN 1 ELSE 0 END) AS high_risk, {$perQuestion}")
            ->first();

        $spread = (clone $base)
            ->selectRaw("{$yes} AS yes, COUNT(*) AS n")
            ->groupByRaw($yes)
            ->pluck('n', 'yes');

        $total = (int) $totals->total;

        return [
            'total' => $total,
            'averageYes' => $total > 0 ? round((float) $totals->average_yes, 1) : null,
            'highRisk' => (int) $totals->high_risk,
            'perQuestion' => collect(range(1, self::QUESTION_COUNT))
                ->mapWithKeys(fn (int $i): array => [$i => (int) $totals->{"q{$i}"}])
                ->all(),
            'yesSpread' => collect(range(0, self::QUESTION_COUNT))
                ->mapWithKeys(fn (int $i): array => [$i => (int) ($spread[$i] ?? 0)])
                ->all(),
            'relationships' => $this->relationships($base),
            'victimSex' => $this->sexes($base, 'a.VictimSex'),
            'offenderSex' => $this->sexes($base, 'a.OffenderSex'),
            'victimAge' => $this->ages($base, 'a.VictimDOB'),
            'offenderAge' => $this->ages($base, 'a.OffenderDOB'),
            'periods' => Chart::perPeriod($base, 'a.DateCreated', $range),
        ];
    }

    // Every option on the fixed list, in its order, zeros included. "Other"
    // counts the code only - the typed detail is free text and never shown.
    private function relationships(Builder $base): array
    {
        $counts = (clone $base)
            ->selectRaw('a.OffenderVictimRelationship AS code, COUNT(*) AS n')
            ->groupBy('a.OffenderVictimRelationship')
            ->pluck('n', 'code');

        $result = [];

        foreach (OffenderRelationship::cases() as $case) {
            $result[$case === OffenderRelationship::Other ? 'Other' : $case->label()] = (int) ($counts[$case->value] ?? 0);
        }

        $unlisted = $counts->filter(fn ($n, $code): bool => OffenderRelationship::tryFrom((string) $code) === null)->sum();

        if ($unlisted > 0) {
            $result['Not given'] = (int) $unlisted;
        }

        return $result;
    }

    private function sexes(Builder $base, string $column): array
    {
        $counts = (clone $base)
            ->selectRaw("{$column} AS sex, COUNT(*) AS n")
            ->groupBy($column)
            ->pluck('n', 'sex');

        $result = [];

        foreach (self::SEXES as $code => $label) {
            $result[$label] = (int) ($counts[$code] ?? 0);
        }

        $unlisted = $counts->filter(fn ($n, $code): bool => ! isset(self::SEXES[$code]))->sum();

        if ($unlisted > 0) {
            $result['Not given'] = (int) $unlisted;
        }

        return $result;
    }

    // Age bands from the date of birth, on the day of submission. Only the
    // band leaves the database. "Not given" appears only when some are missing.
    private function ages(Builder $base, string $column): array
    {
        $age = "TIMESTAMPDIFF(YEAR, {$column}, a.DateCreated)";
        $cases = ["WHEN {$column} IS NULL THEN 'none'"];

        foreach (self::AGE_BANDS as $index => [, $low, $high]) {
            $cases[] = match (true) {
                $low === null => "WHEN {$age} <= {$high} THEN '{$index}'",
                $high === null => "WHEN {$age} >= {$low} THEN '{$index}'",
                default => "WHEN {$age} BETWEEN {$low} AND {$high} THEN '{$index}'",
            };
        }

        $counts = (clone $base)
            ->selectRaw('CASE '.implode(' ', $cases).' END AS band, COUNT(*) AS n')
            ->groupBy('band')
            ->pluck('n', 'band');

        $result = [];

        foreach (self::AGE_BANDS as $index => [$label]) {
            $result[$label] = (int) ($counts[(string) $index] ?? 0);
        }

        if (($counts['none'] ?? 0) > 0) {
            $result['Not given'] = (int) $counts['none'];
        }

        return $result;
    }

    // Each agency's count by its officers' (and police admins') CURRENT
    // agency - the assessment row stores no agency. Every agency is listed,
    // zeros included, busiest first.
    private function agencies(Builder $base): array
    {
        $counts = (clone $base)
            ->leftJoin('law_enforcement_agents as lea', 'lea.user_id', '=', 'a.submitted_by')
            ->selectRaw('lea.agency_id AS agency, COUNT(*) AS n')
            ->groupBy('lea.agency_id')
            ->pluck('n', 'agency');

        $result = Agency::query()->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [$name => (int) ($counts[$id] ?? 0)])
            ->all();

        arsort($result);

        if (($counts[''] ?? 0) > 0) {
            $result['No agency'] = (int) $counts[''];
        }

        return $result;
    }
}
