<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Removes rows models mark Prunable - today, bell alerts read more than 12 hours
// ago (App\Models\StaffNotification). Needs the scheduler running on the server
// (`php artisan schedule:run` every minute); the bell hides them either way.
Schedule::command('model:prune')->daily();

// Follow-ups (App\Services\FollowUps): the 24 h and 48 h "still needs
// follow-up" bell reminders, plus opening any follow-up a submission missed.
Schedule::command('follow-ups:remind')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
