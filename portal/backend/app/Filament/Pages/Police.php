<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

// Officer portal home, carried over from the former Next.js
// portal/frontend/app/police/page.js. Slug stays `police` so /police
// keeps working.
class Police extends Page
{
    protected string $view = 'filament.pages.police';

    protected static ?string $slug = 'police';

    protected static ?string $title = 'Home';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 2;

    // A police admin finds this in their single "Police Admin" group;
    // everyone else keeps the group and position above. Grouping and order
    // only - see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::HOME : parent::getNavigationSort();
    }

    protected Width | string | null $maxContentWidth = Width::Full;

    /*
    Officers and police admins only - NOT admins.

    It opens "Welcome back, {first name}" over a Start New Lethality
    Assessment card for officers, or View Assessments and Personnel Management
    cards for police admins. An admin has their own landing page
    (see App\Filament\StaffLanding) and reaches assessments through
    App\Filament\Resources\AssessmentReview, which is the view-all list
    section 5 of Filament_CMS_Design.md actually grants them. Granting admins
    this page as well put the entire Officer Portal in their sidebar and left
    content management looking like a copy of it.
    */
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
        );
    }

    // The original page draws its own heading.
    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function isOfficer(): bool
    {
        return Filament::auth()->user()?->role === UserRole::LawEnforcement;
    }

    // Officers get the Start New Lethality Assessment card and the
    // past-assessment pointer; police admins get two cards for their own two
    // jobs instead (team plan task 11).
    public function isPoliceAdmin(): bool
    {
        return Filament::auth()->user()?->role === UserRole::PoliceAdmin;
    }

    // The officer's "past assessment" pointer: their own records.
    public function pastAssessmentsUrl(): string
    {
        return LawEnforcementAssessmentResource::getUrl('index');
    }

    // Police admin card: their agency's submissions.
    public function assessmentReviewUrl(): string
    {
        return AssessmentReviewResource::getUrl('index');
    }

    // Police admin card: their agency's officer accounts.
    public function officersUrl(): string
    {
        return OfficerAccountResource::getUrl('index');
    }

    public function getSessionTimeoutMinutes(): int
    {
        return (int) config('session.lifetime');
    }
}
