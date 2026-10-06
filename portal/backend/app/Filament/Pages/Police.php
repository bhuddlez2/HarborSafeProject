<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

// Officer portal home, carried over from the former Next.js
// portal/frontend/app/police/page.js. Slug stays `police` so /staff/police
// keeps working (portal/frontend redirects its old /police URLs here).
class Police extends Page
{
    protected string $view = 'filament.pages.police';

    protected static ?string $slug = 'police';

    protected static ?string $title = 'Home';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 2;

    protected Width | string | null $maxContentWidth = Width::Full;

    /*
    Officers and police admins only - NOT admins.

    This page is an officer tool: it opens "What do you need to do?" over a
    Start New Lethality Assessment card. An admin has their own landing page
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

    // Where the "past assessment" link goes. Police admins land here too but
    // cannot open My Assessments (officers only); their past records are in
    // Assessment review, scoped to their agency.
    public function pastAssessmentsUrl(): string
    {
        return Filament::auth()->user()?->hasActiveRole(UserRole::PoliceAdmin)
            ? AssessmentReviewResource::getUrl('index')
            : LawEnforcementAssessmentResource::getUrl('index');
    }

    public function getSessionTimeoutMinutes(): int
    {
        return (int) config('session.lifetime');
    }
}
