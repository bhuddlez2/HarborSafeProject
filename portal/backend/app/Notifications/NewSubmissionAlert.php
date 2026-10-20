<?php

namespace App\Notifications;

use App\Enums\NewSubmission;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

// One "something new arrived" alert in the staff panel's bell, in Filament's
// format so its notification panel can show it. Written at once rather than
// queued (Filament's own database notification is queued, and nothing runs a
// queue worker here), and never shown as a pop-up: it only appears in the
// bell. See App\Enums\NewSubmission for who gets which.
class NewSubmissionAlert extends Notification
{
    public function __construct(public readonly NewSubmission $submission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->submission->title())
            ->body($this->submission->body())
            ->icon($this->submission->icon())
            ->iconColor('primary')
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->url($this->submission->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
