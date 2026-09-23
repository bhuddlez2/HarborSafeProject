<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ContentManagement extends Page
{
    protected string $view = 'filament.pages.content-management';

    protected static ?string $slug = 'content';

    protected static ?string $title = 'Content management';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedNewspaper;

    // The panel root redirects to the first navigation item the user can see,
    // so this must sort ahead of Police for admins to land here.
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return in_array(Filament::auth()->user()?->role, [
            UserRole::Admin,
            UserRole::Secretary,
        ], true);
    }
}
