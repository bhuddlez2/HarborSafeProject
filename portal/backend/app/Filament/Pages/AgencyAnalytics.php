<?php

namespace App\Filament\Pages;

use App\Filament\Analytics\AnalyticsPage;
use App\Filament\Analytics\AssessmentSections;
use App\Filament\PoliceAdminNavigation;
use App\Services\Analytics\AssessmentStats;
use App\Support\Analytics\DateRange;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
Analytics for a police admin (#67, team decision): the law-enforcement
assessments of their own agency, and nothing else - no civilian assessments,
service feedback, resource requests or follow-ups.

The same page as Harbor Safe's Law-Enforcement Assessments tab
(OfficerAssessmentAnalytics) - date range, filters (no agency), tiles, charts and the
Excel/PDF export all come from AnalyticsPage, AssessmentStats and
AssessmentSections - but every number goes through
LawEnforcementAssessment::visibleTo() (AssessmentStats::officer() with a
viewer), and there is no "By agency" chart, which would name other agencies.

Police admins with an agency only (User::managedAgencyId()); everyone else
gets no sidebar item and a 403. Outside the Analytics cluster, so its tabs
and Harbor Safe's every-agency totals stay out of reach.
*/
class AgencyAnalytics extends AnalyticsPage
{
    protected static ?string $cluster = null;

    protected static ?string $slug = 'police/analytics';

    protected static ?string $title = 'Law-Enforcement Assessments';

    protected static ?string $navigationLabel = 'Analytics';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | UnitEnum | null $navigationGroup = PoliceAdminNavigation::GROUP;

    protected static ?int $navigationSort = PoliceAdminNavigation::ANALYTICS;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->managedAgencyId() !== null;
    }

    public function description(): string
    {
        return ucfirst(self::agencyPossessive()).' law-enforcement assessments only. No names or phone numbers are shown.';
    }

    // Sex and relationship, never agency (FT32's filters; the scope is theirs).
    public function offersFilters(): bool
    {
        return true;
    }

    // The agency first, so a printed copy says whose numbers they are.
    public function exportLines(): array
    {
        return ['Agency: '.(self::agencyName() ?? 'Unknown'), ...parent::exportLines()];
    }

    protected function sections(DateRange $range): array
    {
        $stats = app(AssessmentStats::class)->officer($range, ...$this->filters(), viewer: Filament::auth()->user());

        return AssessmentSections::build($stats, $range, isOfficer: true, filters: $this->filters(), byAgency: false);
    }

    private static function agencyName(): ?string
    {
        $name = trim((string) (Filament::auth()->user()?->officerIdentity()['agency'] ?? ''));

        return $name === '' ? null : $name;
    }

    // "Test PD's", "Example Sheriffs'", or "your agency's".
    private static function agencyPossessive(): string
    {
        $agency = self::agencyName();

        if ($agency === null) {
            return "your agency's";
        }

        return str_ends_with(strtolower($agency), 's') ? "{$agency}'" : "{$agency}'s";
    }
}
