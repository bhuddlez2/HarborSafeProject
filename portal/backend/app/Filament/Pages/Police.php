<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Police extends Page
{
    protected string $view = 'filament.pages.police';

    protected static ?string $slug = 'police';

    protected static ?string $title = 'Police';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(Filament::auth()->user()?->role, [
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
            UserRole::Admin,
        ], true);
    }
}
