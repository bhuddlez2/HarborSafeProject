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

// "Search Records" from the former Next.js officer sidebar. The original was
// a link with no page behind it, so this is a placeholder in the Home page's
// styling until it's designed.
class SearchRecords extends Page
{
    protected string $view = 'filament.pages.officer-placeholder';

    protected static ?string $slug = 'police/search';

    protected static ?string $title = 'Search Records';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 5;

    protected Width | string | null $maxContentWidth = Width::Full;

    // Same roles as My Assessments.
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
}
