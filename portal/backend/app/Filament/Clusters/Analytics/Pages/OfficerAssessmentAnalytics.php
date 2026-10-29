<?php

namespace App\Filament\Clusters\Analytics\Pages;

use App\Enums\UserRole;
use App\Filament\Analytics\AnalyticsPage;
use App\Filament\Analytics\AssessmentSections;
use App\Services\Analytics\AssessmentStats;
use App\Support\Analytics\DateRange;
use Filament\Facades\Filament;

// Analytics tab 1: officer assessments, every agency together. Admins and
// super admins only.
class OfficerAssessmentAnalytics extends AnalyticsPage
{
    protected static ?string $slug = 'officer-assessments';

    protected static ?string $title = 'Law-Enforcement Assessments';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    public function offersSexFilter(): bool
    {
        return true;
    }

    protected function sections(DateRange $range): array
    {
        $stats = app(AssessmentStats::class)->officer($range, ...$this->sexFilter());

        return AssessmentSections::build($stats, $range, isOfficer: true);
    }
}
