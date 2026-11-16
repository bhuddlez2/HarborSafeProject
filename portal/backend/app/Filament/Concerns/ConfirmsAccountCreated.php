<?php

namespace App\Filament\Concerns;

use App\Filament\Accounts\SetupLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/*
How every create-account page (Accounts, Officers) finishes: one Create
button - no "Create & create another" - and on success an "Account Created
Successfully" pop-up with a green check, in place of Filament's corner toast.
Closing it leaves the person on a fresh, empty create form, ready for the
next account.

The new account has no password: its set-up link is emailed here, once the
account is saved (App\Filament\Accounts\SetupLink), and the pop-up says whether it
went. If it didn't, the account is still saved and the list's Resend Set-Up
Link sends it again.

Built on Filament's own create-another path, which saves, resets the form and
stays on the page; the pop-up opens only when afterCreate() has run, so a
failed or halted save never shows it.
*/
trait ConfirmsAccountCreated
{
    protected bool $accountWasCreated = false;

    // Where the set-up link went, for the pop-up; null if it couldn't be sent.
    public ?string $setupLinkSentTo = null;

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
        $this->setupLinkSentTo = SetupLink::send($this->getRecord()) === SetupLink::SENT
            ? $this->getRecord()->email
            : null;
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
            ->modalDescription(fn (): string => $this->setupLinkSentTo !== null
                ? "A set-up link has been emailed to {$this->setupLinkSentTo}. It works once and expires in 24 hours."
                : "The set-up email couldn't be sent. Use Resend Set-Up Link on the list to try again.")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('md');
    }
}
