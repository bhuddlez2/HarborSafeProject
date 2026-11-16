<?php

namespace App\Filament\Forms;

use App\Models\User;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

/*
The layout every account form shares - Accounts (StaffAccountResource) and
Officers (OfficerAccountResource), create and edit alike: a full-width
Details section (Role | Email, then First name | Last name, then anything
role-specific), then the page's buttons. There is no password: a new
account sets its own from an emailed link (App\Filament\Accounts\SetupLink).
*/
final class AccountFormSections
{
    public static function details(array $fields): Section
    {
        return Section::make('Details')
            ->schema($fields)
            ->columns(2)
            ->columnSpanFull();
    }

    // The role, read-only: on edit, and on create wherever the creator has
    // one role to give (admin -> secretary, police admin -> officer). A text
    // box rather than a disabled dropdown so nothing validates or sends it -
    // the pages force the role on the server, so a tampered value is
    // ignored. $role works out which role to show.
    public static function fixedRole(Closure $role): TextInput
    {
        return TextInput::make('role_label')
            ->label('Role')
            ->formatStateUsing(fn (Get $get, ?Model $record): ?string => $role($get, $record)?->label())
            ->disabled()
            ->dehydrated(false);
    }

    // The password rule in words, shown under every new-password box (the
    // Account page and the reset and set-up pages) and in staff:create-super-admin's prompt, so
    // nobody learns the rule only from a refused save.
    public const PASSWORD_HINT = User::PASSWORD_MIN.' to '.User::PASSWORD_MAX.' characters, with at least one capital letter, '
        .'one lowercase letter, one number and one symbol, using only characters on a standard keyboard. '
        .'Passwords found in known data breaches are not accepted.';

    // The rule for a new password set in the panel: on the Account page
    // (EditProfile) and the reset and set-up pages. It is
    // the app-wide Password::defaults() set in AppServiceProvider (15-64
    // characters, a capital, a lowercase letter, a number and a symbol, not
    // found in a breach), so the rule lives in one place.
    public static function passwordRule(): Password
    {
        return Password::default();
    }
}
