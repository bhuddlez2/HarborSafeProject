<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/*
The email carrying a password link: the set-up link for a new account
(App\Filament\Accounts\SetupLink) or the "Forgot password?" reset link (bound
in place of Filament's own in AppServiceProvider). Sent at once, never queued:
nothing runs a queue worker, and Filament's version is queued, so it would
never leave. Holds the link and nothing else about the person.
*/
class StaffPasswordLink extends Notification
{
    // Set by Filament's request page for a reset; given directly for a set-up link.
    public string $url = '';

    public function __construct(public bool $setup = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.($this->setup ? 'staff_setup' : 'users').'.expire');
        $lifetime = $minutes % 60 === 0
            ? trans_choice('{1} 1 hour|[2,*] :count hours', $minutes / 60)
            : trans_choice('{1} 1 minute|[2,*] :count minutes', $minutes);

        return $this->setup
            ? (new MailMessage)
                ->subject('Set Up Your Harbor Safe Portal Account')
                ->line('An account has been created for you on the Harbor Safe Portal. Choose your password to start using it.')
                ->action('Set Your Password', $this->url)
                ->line("This link works once and expires in {$lifetime}. If it has expired, ask the person who created your account to send a new one.")
            : (new MailMessage)
                ->subject('Reset Your Harbor Safe Portal Password')
                ->line('Someone asked to reset the password for your Harbor Safe Portal account.')
                ->action('Reset Your Password', $this->url)
                ->line("This link works once and expires in {$lifetime}. If you didn't ask for it, you can ignore this email: your password stays the same.");
    }
}
