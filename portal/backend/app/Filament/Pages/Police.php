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

    // Content management is 1, so admins land there and officers here.
    protected static ?int $navigationSort = 2;

    protected Width | string | null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return in_array(Filament::auth()->user()?->role, [
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
            UserRole::Admin,
        ], true);
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

    // TODO: replace with a real draft query once drafts are persisted
    // server-side. Carried over as-is from the Next.js stub, which also
    // always returned null.
    public function getOpenDraft(): ?array
    {
        return null;
    }

    public function getSessionTimeoutMinutes(): int
    {
        return (int) config('session.lifetime');
    }
}
