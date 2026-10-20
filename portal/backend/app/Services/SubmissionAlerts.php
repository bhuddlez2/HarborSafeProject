<?php

namespace App\Services;

use App\Enums\NewSubmission;
use App\Models\User;
use App\Notifications\NewSubmissionAlert;
use Illuminate\Support\Facades\Notification;
use Throwable;

// Raises the bell alert for a new submission. Called once the submission is
// saved, by each place one arrives: the website's resource request, feedback
// and civilian assessment endpoints, and the officer wizard.
//
// Never lets an alert get in the way of the submission itself: if alerting
// fails, the error is reported and the submission still succeeds. Recipients
// are looked up now, so a deactivated account never hears of anything.
class SubmissionAlerts
{
    public function raise(NewSubmission $submission): void
    {
        try {
            $recipients = User::query()
                ->where('is_active', true)
                ->whereIn('role', array_map(fn ($role): string => $role->value, $submission->alertedRoles()))
                ->get();

            Notification::send($recipients, new NewSubmissionAlert($submission));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
