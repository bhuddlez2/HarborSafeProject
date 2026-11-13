<?php

namespace App\Filament\Accounts;

use App\Models\User;
use App\Notifications\StaffPasswordLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/*
How a new account gets its password: nobody types one for anyone else. The
account is saved with no password (Pending) and its email address receives a
set-up link, sent when the account is created (ConfirmsAccountCreated) and
again from Resend Set-Up Link on Accounts and Officers. The link opens
App\Filament\Pages\Auth\SetUpPassword, works once and expires after 24 hours
(the staff_setup broker in config/auth.php); a new link replaces the old one.
*/
final class SetupLink
{
    public const SENT = 'sent';

    public const THROTTLED = 'throttled';

    public const FAILED = 'failed';

    // One of the constants above. A mail failure is reported, never thrown:
    // the account is already saved and the link can be sent again.
    public static function send(User $user): string
    {
        try {
            $status = Password::broker('staff_setup')->sendResetLink(
                ['email' => $user->email],
                function (User $user, #[\SensitiveParameter] string $token): void {
                    $notification = new StaffPasswordLink(setup: true);
                    $notification->url = URL::signedRoute('filament.staff.auth.password-setup', [
                        'email' => $user->getEmailForPasswordReset(),
                        'token' => $token,
                    ]);

                    $user->notify($notification);
                },
            );
        } catch (TransportExceptionInterface $e) {
            report($e);

            return self::FAILED;
        }

        return match ($status) {
            Password::RESET_LINK_SENT => self::SENT,
            Password::RESET_THROTTLED => self::THROTTLED,
            default => self::FAILED,
        };
    }

    // Shown under the name on the account tables until the password is set.
    public static function pendingTag(User $user): ?HtmlString
    {
        return $user->hasPendingPassword()
            ? new HtmlString('<span class="hs-staff-tag">Pending</span>')
            : null;
    }

    /**
     * Resend Set-Up Link, for a pending, active account the viewer may edit
     * on that screen. Checked again when it runs, since it is a custom action
     * (CLAUDE.md, Auth).
     *
     * @param  class-string<Resource>  $resource
     */
    public static function resendAction(string $resource): Action
    {
        $allowed = fn (User $record): bool => $record->hasPendingPassword()
            && $record->is_active
            && $resource::canEdit($record);

        return Action::make('resendSetupLink')
            ->label('Resend Set-Up Link')
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible($allowed)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Resend the set-up link?')
            ->modalDescription(fn (User $record): string => "A new link will be emailed to {$record->email}. "
                .'It works once and expires in 24 hours. Any earlier link stops working.')
            ->modalSubmitActionLabel('Send')
            ->action(function (User $record) use ($allowed): void {
                abort_unless($allowed($record), 403);

                match (self::send($record)) {
                    self::SENT => Notification::make()->title('Set-up link sent')->success()->send(),
                    self::THROTTLED => Notification::make()->title('A link was just sent')
                        ->body('Please wait a minute before sending another.')->warning()->send(),
                    default => Notification::make()->title("The email couldn't be sent")
                        ->body('Please try again later.')->danger()->send(),
                };
            });
    }
}
