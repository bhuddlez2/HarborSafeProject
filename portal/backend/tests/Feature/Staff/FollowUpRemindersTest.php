<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\NewSubmission;
use App\Enums\UserRole;
use App\Models\FollowUp;
use App\Notifications\FollowUpReminder;
use App\Notifications\NewSubmissionAlert;
use App\Services\FollowUps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

/*
Follow-up tracking, part 3 (team plan task 16): the 24 h and 48 h "still
needs follow-up" reminders, the hourly run that sends them, and new-submission
alerts that link straight to the form's follow-up page.

Notifications are faked (tests/TestCase.php). Reminder runs are narrowed to
these tests' own follow-ups, so the shared local database's real forms are
never marked as reminded.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// A follow-up of $kind, as if submitted $hours ago.
function waitingFollowUp(FollowUpKind $kind, int $hours, int $remindersSent = 0): FollowUp
{
    $followUp = pestFollowUp($kind);
    $followUp->forceFill(['SubmittedAt' => now()->subHours($hours)->subMinute(), 'RemindersSent' => $remindersSent])->save();

    return $followUp;
}

function remindAbout(FollowUp ...$followUps): int
{
    return app(FollowUps::class)->sendReminders(
        FollowUp::query()->whereKey(array_map(fn (FollowUp $f) => $f->getKey(), $followUps)),
    );
}

function remindersFor(FollowUp $followUp): Closure
{
    return fn (FollowUpReminder $reminder): bool => $reminder->followUp->is($followUp);
}

test('a form waiting 24 hours reminds the people who work it, once', function () {
    $admin = staffUser(UserRole::Admin);
    $secretary = staffUser(UserRole::Secretary);
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Reminder PD'));

    $assessment = waitingFollowUp(FollowUpKind::CivilianAssessment, 24);
    $request = waitingFollowUp(FollowUpKind::ResourceRequest, 24);

    expect(remindAbout($assessment, $request))->toBe(2);

    // Assessments: admins only. Resource requests: secretaries and admins.
    Notification::assertSentTo($admin, FollowUpReminder::class, remindersFor($assessment));
    Notification::assertNotSentTo($secretary, FollowUpReminder::class, remindersFor($assessment));
    Notification::assertSentTo($admin, FollowUpReminder::class, remindersFor($request));
    Notification::assertSentTo($secretary, FollowUpReminder::class, remindersFor($request));
    Notification::assertNotSentTo($officer, FollowUpReminder::class);

    expect($assessment->refresh()->RemindersSent)->toBe(1);

    // Running again an hour later sends nothing new.
    expect(remindAbout($assessment, $request))->toBe(0);
});

test('nothing before 24 hours, a second reminder at 48, then none', function () {
    $admin = staffUser(UserRole::Admin);

    $young = waitingFollowUp(FollowUpKind::CivilianAssessment, 23);
    $second = waitingFollowUp(FollowUpKind::LawEnforcementAssessment, 48, remindersSent: 1);
    $done = waitingFollowUp(FollowUpKind::CivilianAssessment, 200, remindersSent: 2);

    expect(remindAbout($young, $second, $done))->toBe(1);

    Notification::assertSentTo($admin, FollowUpReminder::class, fn (FollowUpReminder $r): bool => $r->followUp->is($second) && $r->hours === 48);
    Notification::assertNotSentTo($admin, FollowUpReminder::class, remindersFor($young));
    Notification::assertNotSentTo($admin, FollowUpReminder::class, remindersFor($done));

    expect($second->refresh()->RemindersSent)->toBe(2)
        ->and($young->refresh()->RemindersSent)->toBe(0);
});

test('if both moments have passed, one reminder goes out, saying 48 hours', function () {
    $admin = staffUser(UserRole::Admin);
    $late = waitingFollowUp(FollowUpKind::CivilianAssessment, 60);

    expect(remindAbout($late))->toBe(1);

    Notification::assertSentToTimes($admin, FollowUpReminder::class, 1);
    Notification::assertSentTo($admin, FollowUpReminder::class, fn (FollowUpReminder $r): bool => $r->hours === 48);
    expect($late->refresh()->RemindersSent)->toBe(2);
});

test('a form someone has started on sends no reminder, even if it was only opened before', function () {
    $admin = staffUser(UserRole::Admin);
    $started = waitingFollowUp(FollowUpKind::CivilianAssessment, 30);
    app(FollowUps::class)->update($started, FollowUpStatus::AttemptedContact, 'Left a voicemail.', $admin);

    expect(remindAbout($started))->toBe(0);
    Notification::assertNothingSent();
});

test('a reminder that fails to send is tried again next time', function () {
    staffUser(UserRole::Admin);
    $waiting = waitingFollowUp(FollowUpKind::CivilianAssessment, 25);

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('bell is down'));
    expect(remindAbout($waiting))->toBe(0)
        ->and($waiting->refresh()->RemindersSent)->toBe(0);
});

test('the reminder links to the form\'s follow-up page and names no one', function () {
    $admin = staffUser(UserRole::Admin);
    $waiting = waitingFollowUp(FollowUpKind::CivilianAssessment, 25);

    $message = (new FollowUpReminder($waiting, 24))->toDatabase($admin);

    expect($message['title'])->toBe('Still needs follow-up')
        ->and($message['body'])->toBe('A civilian assessment submitted 24 hours ago has no follow-up yet.')
        ->and($message['actions'][0]['url'])->toBe($waiting->url())
        ->and(json_encode($message))->not->toContain(STAFF_TEST_PREFIX);
});

test('the reminders run every hour', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'follow-ups:remind'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});

test('a form whose follow-up never opened gets one, with its real submission time', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Missing PD'));
    // Created straight through the model, as if opening had failed.
    $assessment = pestOfficerAssessment($officer, 'Missing');

    app(FollowUps::class)->openMissing();

    $followUp = FollowUp::where('LawEnforcementDocumentID', $assessment->DocumentID)->sole();
    $created = Illuminate\Support\Facades\DB::connection('Portal')->table('law_enforcement_assessment')
        ->where('DocumentID', $assessment->DocumentID)->value('DateCreated');

    expect($followUp->Status)->toBe(FollowUpStatus::NeedsFollowUp)
        ->and($followUp->SubmittedAt->toDateTimeString())->toBe(Illuminate\Support\Carbon::parse($created)->toDateTimeString());
});

test('a new assessment or resource request alert opens its follow-up page; feedback opens its list', function () {
    $admin = staffUser(UserRole::Admin);

    $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertCreated();
    $request = FollowUp::where('Kind', FollowUpKind::ResourceRequest->value)->latest('SubmittedAt')->first();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 4,
    ])->assertCreated();

    Notification::assertSentTo($admin, NewSubmissionAlert::class, fn (NewSubmissionAlert $alert): bool => $alert->submission === NewSubmission::ResourceRequest
        && $alert->toDatabase($admin)['actions'][0]['url'] === $request->url());
    Notification::assertSentTo($admin, NewSubmissionAlert::class, fn (NewSubmissionAlert $alert): bool => $alert->submission === NewSubmission::ServiceFeedback
        && $alert->toDatabase($admin)['actions'][0]['url'] === NewSubmission::ServiceFeedback->url());
});
