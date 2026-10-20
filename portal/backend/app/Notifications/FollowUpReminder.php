<?php

namespace App\Notifications;

use App\Models\FollowUp;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Notification;

// "Still needs follow-up": the bell reminder for a form left in Needs
// follow-up 24 hours, and again at 48 (App\Services\FollowUps::
// sendReminders()). Like every bell alert: written at once, never a pop-up,
// no names - just what kind of form and how long, with a link to its
// follow-up page.
class FollowUpReminder extends Notification
{
    public function __construct(
        public readonly FollowUp $followUp,
        public readonly int $hours,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $kind = strtolower($this->followUp->Kind->label());

        return FilamentNotification::make()
            ->title('Still needs follow-up')
            ->body("A {$kind} submitted {$this->hours} hours ago has no follow-up yet.")
            ->icon(Heroicon::OutlinedClock)
            ->iconColor('warning')
            ->actions([
                Action::make('open')
                    ->label('Open')
                    ->url($this->followUp->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
