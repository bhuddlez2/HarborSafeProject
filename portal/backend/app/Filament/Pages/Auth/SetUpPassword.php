<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/*
Where a new account's emailed set-up link lands (App\Filament\Accounts\SetupLink):
the reset page with its own wording, checking the link against the
staff_setup broker (24 hours) instead of the reset one (15 minutes). Each
kind of link has its own token table, so neither page accepts the other's.
Registered in StaffPanelProvider at /set-password, behind 'signed'.
*/
class SetUpPassword extends ResetPassword
{
    public function getTitle(): string | Htmlable
    {
        return 'Set Up Your Password';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Set Up Your Password';
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('Password');
    }

    // Filament's reset reads the broker from the panel, so the panel points at
    // staff_setup for this one call and is put back straight after.
    public function resetPassword(): ?PasswordResetResponse
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $broker = $panel->getAuthPasswordBroker();
        $panel->authPasswordBroker('staff_setup');

        try {
            return parent::resetPassword();
        } finally {
            $panel->authPasswordBroker($broker);
        }
    }
}
