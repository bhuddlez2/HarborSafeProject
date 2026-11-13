<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Forms\AccountFormSections;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

/*
Where the emailed "Forgot password?" link lands, in the sign-in page's look.
Filament checks the link (signed, works once, 15 minutes - config/auth.php)
and refuses inactive accounts; the new password follows the app's one rule
(Password::defaults(), which Filament's box already uses) and its 64-character
cap. SetUpPassword is the same page for a new account's link.
*/
class ResetPassword extends BaseResetPassword
{
    protected string $view = 'filament.pages.auth.login';

    protected Width | string | null $maxWidth = Width::Medium;

    public function hasLogo(): bool
    {
        return false;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Reset Your Password';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Reset Your Password';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->label('Email');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('New password')
            ->helperText(AccountFormSections::PASSWORD_HINT)
            ->maxLength(User::PASSWORD_MAX);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()->maxLength(User::PASSWORD_MAX);
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()->label('Save Password');
    }
}
