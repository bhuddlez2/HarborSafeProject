<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/*
"Forgot password?" from the sign-in page, in the sign-in page's look. Filament
sends the link (as App\Notifications\StaffPasswordLink, bound in
AppServiceProvider) only to an active account. Every request gets the same
answer and leaves the box empty, whether or not the address has an account,
is inactive or was just sent a link, so the page can't be used to find out
who has one.
*/
class RequestPasswordReset extends BaseRequestPasswordReset
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

    public function loginAction(): Action
    {
        return parent::loginAction()->label('Back to sign in');
    }

    public function request(): void
    {
        try {
            parent::request();
        } catch (TransportExceptionInterface $e) {
            // Only an existing account gets this far, so the answer stays the same.
            report($e);
            $this->getSentNotification('')?->send();
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([$this->getEmailFormComponent()]);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email')
            ->maxLength(User::EMAIL_MAX)
            ->helperText("Enter your account's email address and we'll email you a link to choose a new password.");
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification($status);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        $minutes = config('auth.passwords.users.expire');

        return Notification::make()
            ->title('Check your email')
            ->body("If an active account uses that address, a reset link is on its way. It works once and expires in {$minutes} minutes.")
            ->success();
    }
}
