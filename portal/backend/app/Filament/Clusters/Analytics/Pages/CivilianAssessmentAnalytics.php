<?php

namespace App\Filament\Clusters\Analytics\Pages;

use App\Enums\UserRole;
use App\Filament\Analytics\AnalyticsPage;
use App\Filament\Analytics\AssessmentSections;
use App\Services\Analytics\AssessmentStats;
use App\Support\Analytics\DateRange;
use Filament\Facades\Filament;

// Analytics tab 2: assessments from the public website. Admins and super
// admins only, like the Civilian assessments screen.
class CivilianAssessmentAnalytics extends AnalyticsPage
{
    protected static ?string $slug = 'civilian-assessments';

    protected static ?string $title = 'Civilian Assessments';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    public function description(): string
    {
        return 'Totals for assessments submitted on the public website. No names or phone numbers are shown.';
    }

    public function offersSexFilter(): bool
    {
        return true;
    }

    protected function sections(DateRange $range): array
    {
        $stats = app(AssessmentStats::class)->civilian($range, ...$this->sexFilter());

        return AssessmentSections::build($stats, $range, isOfficer: false);
    }
}
