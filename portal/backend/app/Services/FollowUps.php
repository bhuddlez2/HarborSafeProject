<?php

namespace App\Services;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\FollowUpEntry;
use App\Models\ResourceRequestForm;
use App\Models\User;
use App\Notifications\FollowUpReminder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/*
The one way a form's follow-up is opened or moved on (team plan task 16;
roadmap Phase Z). The rules, as agreed with AS (2026-10-20):

- Every new assessment and resource request opens a follow-up in Needs
  follow-up. Service feedback never does.
- Changing the status needs a note. A note can also be added on its own, at
  any stage including a final one, and leaves the status where it is.
- Nothing returns to Needs Follow-Up. Could Not Reach, Declined and Closed are
  final for every kind: a final form can't change status. Reached may move on
  only to Closed (Finishing Touches item 25). FollowUpStatus::canMoveTo()
  holds the rule.
- Only the people who work a kind may update it: admins for assessments,
  secretaries and admins for resource requests (FollowUpKind::workedBy()).
- Every update is an append-only entry - who, when, note, and the status
  change if any - and records who last updated the form and when.
*/
class FollowUps
{
    public const NOTE_MAX = 2000;

    // Hours after submission at which a form still in Needs Follow-Up sends a
    // bell reminder - once each, then never again (AS, 2026-10-20).
    public const REMINDER_HOURS = [24, 48];

    /*
    Sends the "still needs follow-up" reminders that are due (hourly, from
    `php artisan follow-ups:remind`). Only forms still in Needs Follow-Up:
    opening a form doesn't count, only a status change. If both moments have
    passed at once (the scheduler was down), one reminder goes out, saying 48
    hours. A reminder only counts as sent once it has been written, so a
    failure is retried on the next run. Returns how many went out.

    $among narrows which follow-ups are considered (tests use it to stay off
    the shared local database's real forms); the scheduled run checks all.
    */
    public function sendReminders(?Builder $among = null): int
    {
        $sent = 0;
        $now = now();

        ($among ?? FollowUp::query())
            ->where('Status', FollowUpStatus::NeedsFollowUp->value)
            ->where('RemindersSent', '<', count(self::REMINDER_HOURS))
            ->where('SubmittedAt', '<=', $now->copy()->subHours(self::REMINDER_HOURS[0]))
            ->orderBy('SubmittedAt')
            ->each(function (FollowUp $followUp) use ($now, &$sent): void {
                $due = count(array_filter(
                    self::REMINDER_HOURS,
                    fn (int $hours): bool => $followUp->SubmittedAt->lte($now->copy()->subHours($hours)),
                ));

                if ($due <= $followUp->RemindersSent) {
                    return;
                }

                try {
                    Notification::send(
                        SubmissionAlerts::recipients($followUp->Kind->submission()),
                        new FollowUpReminder($followUp, self::REMINDER_HOURS[$due - 1]),
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    return;
                }

                $followUp->forceFill(['RemindersSent' => $due])->save();
                $sent++;
            });

        return $sent;
    }

    /*
    Starts a new resource request from a form whose follow-up is final (AS,
    2026-10-20): "New request from this one" on a resource request, or - admins
    only, since only they work assessments - "New resource request for this
    victim" on an assessment. Never a new assessment.

    The request is saved as staff-entered (ResourceRequestForm::
    recordStaffEntry(), the website's own rules) and opens its own follow-up
    in Needs Follow-Up, so the 24 h / 48 h reminders apply. No new-submission
    alert: whoever started it already knows.

    @throws AuthorizationException when $by may not do this
    @throws ValidationException on what the website would refuse too
    */
    public function startResourceRequest(FollowUp $from, array $data, User $by): FollowUp
    {
        if (! self::canStartResourceRequest($from, $by)) {
            throw new AuthorizationException('You can\'t start a request from this form.');
        }

        $request = ResourceRequestForm::recordStaffEntry($data, $by, $from);

        return FollowUp::firstOrCreate(
            ['ResourceRequestFormID' => $request->FormID],
            [
                'Kind' => FollowUpKind::ResourceRequest,
                'Status' => FollowUpStatus::NeedsFollowUp,
                'SubmittedAt' => now(),
                'RemindersSent' => 0,
            ],
        );
    }

    // A final form, worked by $by, who also works resource requests: admins
    // from any kind, secretaries from resource requests only.
    public static function canStartResourceRequest(FollowUp $from, ?User $by): bool
    {
        return $from->Status->isFinal()
            && $from->isWorkableBy($by)
            && in_array(FollowUpKind::ResourceRequest, FollowUp::kindsFor($by), true);
    }

    /*
    Opens a follow-up for any assessment or resource request that has none -
    the safety net for a submission whose open() failed. Uses the form's real
    submission time, so a form found late still gets its reminders. Returns
    how many were opened.
    */
    public function openMissing(): int
    {
        $portal = DB::connection('Portal');
        $opened = 0;

        $sources = [
            [FollowUpKind::LawEnforcementAssessment, $portal->table('law_enforcement_assessment')->select(['DocumentID as id', 'DateCreated as at'])],
            [FollowUpKind::CivilianAssessment, $portal->table('_private_assessment')->select(['DocumentID as id', 'DateCreated as at'])],
            [FollowUpKind::ResourceRequest, DB::connection('Feedback')->table('resource_request_form')->select(['FormID as id', 'SubmissionDate as at'])],
        ];

        foreach ($sources as [$kind, $forms]) {
            $existing = $portal->table('follow_ups')->whereNotNull($kind->pointerColumn())->pluck($kind->pointerColumn())->flip();

            foreach ($forms->get() as $form) {
                if (isset($existing[$form->id])) {
                    continue;
                }

                FollowUp::create([
                    'Kind' => $kind,
                    $kind->pointerColumn() => $form->id,
                    'Status' => FollowUpStatus::NeedsFollowUp,
                    'SubmittedAt' => Carbon::parse($form->at ?? now(), 'UTC'),
                    'RemindersSent' => 0,
                ]);
                $opened++;
            }
        }

        return $opened;
    }

    // Opens the follow-up for a form just submitted. Never lets a failure stop
    // the submission itself: it is reported instead (and the form can be
    // opened again later; opening is idempotent).
    public function open(FollowUpKind $kind, string $formId): ?FollowUp
    {
        try {
            return FollowUp::firstOrCreate(
                [$kind->pointerColumn() => $formId],
                [
                    'Kind' => $kind,
                    'Status' => FollowUpStatus::NeedsFollowUp,
                    'SubmittedAt' => now(),
                    'RemindersSent' => 0,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Records a status change with its note, or - when $status is null or the
     * current status - a note on its own.
     *
     * @throws AuthorizationException when $by doesn't work this kind of form
     * @throws ValidationException on a missing note or a move the rules refuse
     */
    public function update(FollowUp $followUp, ?FollowUpStatus $status, ?string $note, User $by): FollowUpEntry
    {
        if (! $followUp->isWorkableBy($by)) {
            throw new AuthorizationException('You can\'t update this follow-up.');
        }

        $note = trim((string) $note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => $status !== null && $status !== $followUp->Status
                    ? 'Add a note to change the status.'
                    : 'Write a note to add.',
            ]);
        }

        if (mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages([
                'note' => 'Keep the note to '.self::NOTE_MAX.' characters.',
            ]);
        }

        // The row is locked and its status read again inside the transaction,
        // so two people updating the same form at once can't both move it.
        return DB::connection('Portal')->transaction(function () use ($followUp, $status, $note, $by): FollowUpEntry {
            $current = FollowUp::query()->lockForUpdate()->findOrFail($followUp->getKey());
            $changes = $status !== null && $status !== $current->Status;

            if ($changes && ! $current->Status->canMoveTo($status)) {
                throw ValidationException::withMessages([
                    'status' => 'This '.strtolower($current->Kind->label())." can't move from {$current->Status->label()} to {$status->label()}.",
                ]);
            }

            $entry = FollowUpEntry::create([
                'FollowUpID' => $current->getKey(),
                'FromStatus' => $changes ? $current->Status : null,
                'Status' => $changes ? $status : $current->Status,
                'Note' => $note,
                'EnteredBy' => $by->getKey(),
                'EnteredAt' => now(),
            ]);

            $current->forceFill([
                'Status' => $changes ? $status : $current->Status,
                'LastUpdatedAt' => now(),
                'LastUpdatedBy' => $by->getKey(),
            ])->save();

            $followUp->setRawAttributes($current->getAttributes(), true);

            return $entry;
        });
    }
}
