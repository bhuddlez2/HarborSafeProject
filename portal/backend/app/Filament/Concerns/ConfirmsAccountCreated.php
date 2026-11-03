<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/*
How every create-account page (Accounts, Officers) finishes: one Create
button - no "Create & create another" - and on success an "Account Created
Successfully" pop-up with a green check, in place of Filament's corner toast.
Closing it leaves the person on a fresh, empty create form, ready for the
next account.

Built on Filament's own create-another path, which saves, resets the form and
stays on the page; the pop-up opens only when afterCreate() has run, so a
failed or halted save never shows it.
*/
trait ConfirmsAccountCreated
{
    protected bool $accountWasCreated = false;

    public function create(bool $another = false): void
    {
        $this->accountWasCreated = false;

        parent::create(another: true);

        if ($this->accountWasCreated) {
            $this->mountAction('accountCreated');
        }
    }

    protected function afterCreate(): void
    {
        $this->accountWasCreated = true;
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    public function accountCreatedAction(): Action
    {
        return Action::make('accountCreated')
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalIconColor('success')
            ->modalHeading('Account Created Successfully')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('md');
    }
}
