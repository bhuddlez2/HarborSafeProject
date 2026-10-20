<?php

namespace App\Console\Commands;

use App\Services\FollowUps;
use Illuminate\Console\Command;

// Hourly (routes/console.php): opens any follow-up a submission failed to
// open, then sends the 24 h and 48 h "still needs follow-up" bell reminders.
// Needs the scheduler running on the server (`php artisan schedule:run` every
// minute); can also be run by hand.
class SendFollowUpReminders extends Command
{
    protected $signature = 'follow-ups:remind';

    protected $description = 'Open any missing follow-ups, then send the 24 h and 48 h "still needs follow-up" reminders';

    public function handle(FollowUps $followUps): int
    {
        $opened = $followUps->openMissing();
        $sent = $followUps->sendReminders();

        $this->info("Opened {$opened} missing follow-up(s); sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
