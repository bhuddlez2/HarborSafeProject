<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

// "My Assessments" from the former Next.js officer sidebar. The original was
// a link with no page behind it, so this is a placeholder in the Home page's
// styling until it's designed.
class MyAssessments extends Page
{
    protected string $view = 'filament.pages.my-assessments';

    protected static ?string $slug = 'police/assessments';

    protected static ?string $title = 'My Assessments';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 4;

    protected Width | string | null $maxContentWidth = Width::Full;

    // Access matrix: officers see their own, police admins and admins see all.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
            UserRole::Admin,
        );
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function getSessionTimeoutMinutes(): int
    {
        return (int) config('session.lifetime');
    }
}
