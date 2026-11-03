<?php

namespace App\Services\Analytics;

use App\Enums\OffenderRelationship;
use App\Models\Agency;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;
use App\Support\HighRisk;
use App\Support\SensitiveData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/*
The numbers behind Analytics' two assessment tabs (team plan task 28): officer
assessments (law_enforcement_assessment) and civilian ones
(_private_assessment), both joined to their eleven answers. Totals only:
nothing here selects a name or phone number, and no date of birth, sex or
relationship leaves this class except as a count.

The answers are counted in SQL, a handful of grouped queries per tab, on the
full-access Portal connection (the page is for Harbor Safe staff, who may open
every record anyway). Sex, dates of birth and relationship are encrypted
(App\Support\SensitiveData), so the database can't group or filter on them:
people() reads them for the date range, decrypts them here, and they are
counted in PHP. Every count narrows to the date range on DateCreated and to
whichever of the page's filters are set: victim sex, offender sex and
relationship are matched in PHP, then applied to the SQL counts as a list of
DocumentIDs; agency (officer assessments) is plain SQL.

High risk is App\Support\HighRisk's rule (a yes to any of Q1-3, or 4 or more
yes in all), the same one the assessment tables' red badge uses.

officer() with a $viewer counts only the assessments that viewer may see
(LawEnforcementAssessment::visibleTo()) - a police admin's own agency, for
their Analytics page (App\Filament\Pages\AgencyAnalytics, #67). The scope is
applied to the base query, before anything is read or decrypted, so every
total and chart follows it; "By agency" is left out, since it would name
other agencies. Harbor Safe's own tab passes no viewer and counts everything.
*/
final class AssessmentStats
{
    public const QUESTION_COUNT = 11;

    public const HIGH_RISK_RULE = HighRisk::RULE;

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

    public function officer(DateRange $range, ?string $victimSex = null, ?string $offenderSex = null, ?string $relationship = null, ?int $agency = null, ?User $viewer = null): array
    {
        $scope = $viewer === null ? null : LawEnforcementAssessment::query()
            ->visibleTo($viewer)
            ->select('law_enforcement_assessment.DocumentID')
            ->toBase();

        [$base, $people] = $this->base('law_enforcement_assessment', $range, $victimSex, $offenderSex, $relationship, $agency, $scope);

        return [
            ...$this->common($base, $people, $range),
            'edited' => (clone $base)
                ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('assessment_edits as e')->whereColumn('e.DocumentID', 'a.DocumentID'))
                ->count(),
            'agencies' => $viewer === null ? $this->agencies($base) : [],
        ];
    }

    public function civilian(DateRange $range, ?string $victimSex = null, ?string $offenderSex = null, ?string $relationship = null): array
    {
        [$base, $people] = $this->base('_private_assessment', $range, $victimSex, $offenderSex, $relationship);

        return [
            ...$this->common($base, $people, $range),
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
        return HighRisk::sql('x');
    }

    // The relationship list as Analytics names it: "Other" without the
    // form's "(Please Specify)". Code => label, in list order.
    /** @return array<string, string> */
    public static function relationshipOptions(): array
    {
        return collect(OffenderRelationship::cases())
            ->mapWithKeys(fn (OffenderRelationship $case): array => [$case->value => $case === OffenderRelationship::Other ? 'Other' : $case->label()])
            ->all();
    }

    /**
     * The SQL base for the answer counts, and the decrypted people columns of
     * the same rows, both narrowed to the filters. Agency (officer
     * assessments only) is the submitter's current agency, in SQL; the rest
     * are encrypted, so they are matched in PHP.
     *
     * @return array{0: Builder, 1: list<array<string, ?string>>}
     */
    private function base(string $table, DateRange $range, ?string $victimSex, ?string $offenderSex, ?string $relationship, ?int $agency = null, ?Builder $scope = null): array
    {
        $base = DB::connection('Portal')->table("{$table} as a")
            ->join('_assessment_answers as x', 'x.AssessmentDocID', '=', 'a.AssessmentDocID')
            ->whereBetween('a.DateCreated', $range->between());

        if ($agency !== null) {
            $base->whereIn('a.submitted_by', fn (Builder $q) => $q->select('user_id')->from('law_enforcement_agents')->where('agency_id', $agency));
        }

        // A viewer's scope (officer() with a $viewer): their visible DocumentIDs.
        if ($scope !== null) {
            $base->whereIn('a.DocumentID', $scope);
        }

        $people = $this->people($base);

        if ($victimSex !== null || $offenderSex !== null || $relationship !== null) {
            $people = array_values(array_filter($people, fn (array $row): bool => ($victimSex === null || $row['VictimSex'] === $victimSex)
                && ($offenderSex === null || $row['OffenderSex'] === $offenderSex)
                && ($relationship === null || $row['OffenderVictimRelationship'] === $relationship)));

            $base->whereIn('a.DocumentID', array_column($people, 'DocumentID'));
        }

        return [$base, $people];
    }

    // The encrypted columns Analytics counts, decrypted, one row per
    // assessment. Never names, phone numbers or the "Other" relationship text.
    /** @return list<array<string, ?string>> */
    private function people(Builder $base): array
    {
        $columns = ['VictimSex', 'OffenderSex', 'VictimDOB', 'OffenderDOB', 'OffenderVictimRelationship'];

        return (clone $base)
            ->select(['a.DocumentID', 'a.DateCreated', ...array_map(fn (string $c): string => "a.{$c}", $columns)])
            ->get()
            ->map(function (object $row) use ($columns): array {
                $person = ['DocumentID' => (string) $row->DocumentID, 'DateCreated' => (string) $row->DateCreated];

                foreach ($columns as $column) {
                    $person[$column] = SensitiveData::decrypt($row->{$column});
                }

                return $person;
            })
            ->all();
    }

    private function common(Builder $base, array $people, DateRange $range): array
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
            'relationships' => $this->relationships($people),
            'victimSex' => $this->sexes($people, 'VictimSex'),
            'offenderSex' => $this->sexes($people, 'OffenderSex'),
            'victimAge' => $this->ages($people, 'VictimDOB'),
            'offenderAge' => $this->ages($people, 'OffenderDOB'),
            'periods' => Chart::perPeriod($base, 'a.DateCreated', $range),
        ];
    }

    // Every option on the fixed list, in its order, zeros included. "Other"
    // counts the code only - the typed detail is free text and never shown.
    private function relationships(array $people): array
    {
        $counts = self::countBy($people, 'OffenderVictimRelationship');

        $result = [];

        foreach (self::relationshipOptions() as $code => $label) {
            $result[$label] = (int) ($counts[$code] ?? 0);
        }

        $unlisted = $counts->filter(fn ($n, $code): bool => OffenderRelationship::tryFrom((string) $code) === null)->sum();

        if ($unlisted > 0) {
            $result['Not given'] = (int) $unlisted;
        }

        return $result;
    }

    private function sexes(array $people, string $column): array
    {
        $counts = self::countBy($people, $column);

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

    // Age bands from the date of birth, on the day of submission (whole years,
    // as MariaDB's TIMESTAMPDIFF(YEAR) counts them). Only the band leaves this
    // class. "Not given" appears only when some are missing.
    private function ages(array $people, string $column): array
    {
        $counts = collect($people)->countBy(function (array $row) use ($column): string {
            if (blank($row[$column])) {
                return 'none';
            }

            $span = Carbon::parse($row[$column], 'UTC')->startOfDay()->diff(Carbon::parse($row['DateCreated'], 'UTC'));
            $age = $span->invert ? -$span->y : $span->y;

            foreach (self::AGE_BANDS as $index => [, $low, $high]) {
                if (($low === null || $age >= $low) && ($high === null || $age <= $high)) {
                    return (string) $index;
                }
            }

            return 'none';
        });

        $result = [];

        foreach (self::AGE_BANDS as $index => [$label]) {
            $result[$label] = (int) ($counts[(string) $index] ?? 0);
        }

        if (($counts['none'] ?? 0) > 0) {
            $result['Not given'] = (int) $counts['none'];
        }

        return $result;
    }

    // How many people have each value of $column; a missing one counts under ''.
    /** @return Collection<string, int> */
    private static function countBy(array $people, string $column): Collection
    {
        return collect($people)->countBy(fn (array $row): string => (string) ($row[$column] ?? ''));
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
