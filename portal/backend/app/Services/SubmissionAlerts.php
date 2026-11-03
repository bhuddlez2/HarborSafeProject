<?php

namespace App\Services;

use App\Enums\NewSubmission;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\NewSubmissionAlert;
use Illuminate\Support\Facades\Notification;
use Throwable;

// Raises the bell alert for a new submission. Called once the submission is
// saved, by each place one arrives: the website's resource request, feedback
// and civilian assessment endpoints, and the officer wizard.
//
// An assessment or resource request alert links to the form's follow-up page
// ($followUp), so staff can read it and start at once; feedback has no
// follow-up and links to its list.
//
// Never lets an alert get in the way of the submission itself: if alerting
// fails, the error is reported and the submission still succeeds. Recipients
// are looked up now, so a deactivated account never hears of anything.
class SubmissionAlerts
{
    public function raise(NewSubmission $submission, ?FollowUp $followUp = null): void
    {
        try {
            Notification::send(self::recipients($submission), new NewSubmissionAlert($submission, $followUp?->url()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    // Active users in the roles this kind of submission alerts.
    public static function recipients(NewSubmission $submission)
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', array_map(fn ($role): string => $role->value, $submission->alertedRoles()))
            ->get();
    }
}
