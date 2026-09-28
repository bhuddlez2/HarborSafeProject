<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

// Filament's login with the look of the former Next.js sign-in page
// (portal/frontend/app/login/page.js). Authentication itself - authenticate(),
// rate limiting, canAccessPanel() - is inherited untouched; only the copy,
// the view and the field list change.
class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected Width | string | null $maxWidth = Width::Medium;

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Sign in';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'HarborSafe staff portal';
    }

    // The original had plain Email/Password fields: no "Remember me" (without
    // it Filament's authenticate() signs in with remember = false), no
    // required asterisks and no show-password toggle.
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent()
                    ->label('Email')
                    ->markAsRequired(false),
                $this->getPasswordFormComponent()
                    ->label('Password')
                    ->markAsRequired(false)
                    ->revealable(false),
            ]);
    }
}
