<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Models\FollowUp;
use App\Models\FollowUpEntry;
use App\Services\FollowUps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
Follow-up tracking, part 1 (team plan task 16; roadmap Phase Z): what opens a
follow-up, how a follow-up may move, and who may move it. The rules live in
App\Services\FollowUps and App\Enums\FollowUpStatus::canMoveTo().
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function followUps(): FollowUps
{
    return app(FollowUps::class);
}

test('every new assessment and resource request opens a follow-up in Needs follow-up', function () {
    // Website resource request.
    $requestId = $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertCreated()->json('FormID');

    // Website civilian assessment.
    $this->postJson('/api/public/civilian-assessments', [
        'ForWhom' => 'self',
        ...collect(range(1, 11))->mapWithKeys(fn (int $n): array => ["RiskIndicator{$n}" => false])->all(),
        'VictimFirstName' => STAFF_TEST_PREFIX.'Victim',
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        // Required, as on the website.
        'VictimDOB' => '1990-04-12',
        'VictimSafePhoneNumber' => '(423)555-0100',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
    ])->assertCreated();
    $civilian = encryptedRows(App\Models\PrivateAssessment::class, 'VictimFirstName', STAFF_TEST_PREFIX.'Victim')->sole();

    // Officer wizard.
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Wizard PD'));
    $this->actingAs($officer);
    Livewire::test(NewAssessment::class)
        ->fillForm([
            ...collect(range(1, 11))->mapWithKeys(fn (int $n): array => ["RiskIndicator{$n}" => false])->all(),
            'VictimFirstName' => 'Test', 'VictimLastName' => 'Victim', 'VictimDOB' => '1990-01-01', 'VictimSex' => 'F',
            'VictimSafePhoneNumber' => '(423)555-0100',
            'OffenderFirstName' => 'Test', 'OffenderLastName' => 'Offender', 'OffenderSex' => 'M',
            'OffenderVictimRelationship' => 'spouse',
            'SafePhoneConfirmed' => true,
        ])
        ->call('create')
        ->assertSet('submitted', true);
    $officerAssessment = App\Models\LawEnforcementAssessment::where('submitted_by', $officer->getKey())->sole();

    foreach ([
        ['ResourceRequestFormID', $requestId, FollowUpKind::ResourceRequest],
        ['PrivateDocumentID', $civilian->DocumentID, FollowUpKind::CivilianAssessment],
        ['LawEnforcementDocumentID', $officerAssessment->DocumentID, FollowUpKind::LawEnforcementAssessment],
    ] as [$column, $id, $kind]) {
        $followUp = FollowUp::where($column, $id)->sole();

        expect($followUp->Kind)->toBe($kind)
            ->and($followUp->Status)->toBe(FollowUpStatus::NeedsFollowUp)
            ->and($followUp->SubmittedAt)->not->toBeNull()
            ->and($followUp->RemindersSent)->toBe(0)
            ->and($followUp->LastUpdatedBy)->toBeNull()
            ->and($followUp->entries)->toBeEmpty();
    }
});

test('service feedback never opens a follow-up', function () {
    $before = FollowUp::count();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 4,
    ])->assertCreated();

    expect(FollowUp::count())->toBe($before);
});

test('opening a form\'s follow-up twice keeps the one', function () {
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);

    expect(followUps()->open(FollowUpKind::CivilianAssessment, $followUp->PrivateDocumentID)->getKey())
        ->toBe($followUp->getKey());
});

test('a status change needs a note, and is recorded with who and when', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);

    expect(fn () => followUps()->update($followUp, FollowUpStatus::AttemptedContact, '   ', $admin))
        ->toThrow(ValidationException::class);

    $entry = followUps()->update($followUp, FollowUpStatus::AttemptedContact, "  Left a voicemail.  \n", $admin);
    $followUp->refresh();

    expect($followUp->Status)->toBe(FollowUpStatus::AttemptedContact)
        ->and($followUp->LastUpdatedBy)->toBe($admin->getKey())
        ->and($followUp->LastUpdatedAt)->not->toBeNull()
        ->and($entry->FromStatus)->toBe(FollowUpStatus::NeedsFollowUp)
        ->and($entry->Status)->toBe(FollowUpStatus::AttemptedContact)
        ->and($entry->Note)->toBe('Left a voicemail.')
        ->and($entry->EnteredBy)->toBe($admin->getKey())
        ->and($entry->EnteredAt)->not->toBeNull();
});

test('a note can be added on its own, at any stage, without moving the form', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);

    // In Needs follow-up: no status given, and the current one given, are both a note.
    followUps()->update($followUp, null, 'Tried the number, no answer yet.', $admin);
    followUps()->update($followUp, FollowUpStatus::NeedsFollowUp, 'Will try again tomorrow.', $admin);
    expect($followUp->refresh()->Status)->toBe(FollowUpStatus::NeedsFollowUp);

    // In a final status too.
    followUps()->update($followUp, FollowUpStatus::Closed, 'Spoke with her; referred to shelter.', $admin);
    followUps()->update($followUp, null, 'Checked in a week later.', $admin);

    $entries = $followUp->refresh()->entries;

    expect($followUp->Status)->toBe(FollowUpStatus::Closed)
        ->and($entries)->toHaveCount(4)
        ->and($entries->pluck('Note')->all())->toBe([
            'Tried the number, no answer yet.',
            'Will try again tomorrow.',
            'Spoke with her; referred to shelter.',
            'Checked in a week later.',
        ])
        ->and($entries->map(fn (FollowUpEntry $e): bool => $e->changedStatus())->all())->toBe([false, false, true, false]);
});

test('where a form may move from each status, the same for every kind of form', function (FollowUpStatus $from, array $to) {
    expect($from->next())->toBe($to);

    $admin = staffUser(UserRole::Admin);

    foreach (FollowUpKind::cases() as $kind) {
        $followUp = pestFollowUp($kind);

        foreach (FollowUpStatus::cases() as $status) {
            if ($status === $from) {
                continue;
            }

            $followUp->forceFill(['Status' => $from])->save();
            $attempt = fn () => followUps()->update($followUp->refresh(), $status, 'Trying a move.', $admin);

            if (in_array($status, $to, true)) {
                $attempt();
                expect($followUp->refresh()->Status)->toBe($status);
            } else {
                expect($attempt)->toThrow(ValidationException::class);
            }
        }
    }
})->with([
    // Anywhere but back to itself, Closed included.
    'Needs Follow-Up' => [FollowUpStatus::NeedsFollowUp, [FollowUpStatus::AttemptedContact, FollowUpStatus::Reached, FollowUpStatus::CouldNotReach, FollowUpStatus::Declined, FollowUpStatus::Closed]],
    'Attempted Contact' => [FollowUpStatus::AttemptedContact, [FollowUpStatus::Reached, FollowUpStatus::CouldNotReach, FollowUpStatus::Declined, FollowUpStatus::Closed]],
    // Not final, but on to Closed only.
    'Reached' => [FollowUpStatus::Reached, [FollowUpStatus::Closed]],
    // Final: no status change at all.
    'Could Not Reach' => [FollowUpStatus::CouldNotReach, []],
    'Declined' => [FollowUpStatus::Declined, []],
    'Closed' => [FollowUpStatus::Closed, []],
]);

test('Could Not Reach, Declined and Closed are the final statuses, every status in title case', function () {
    expect(array_values(array_filter(FollowUpStatus::cases(), fn (FollowUpStatus $s): bool => $s->isFinal())))
        ->toBe([FollowUpStatus::CouldNotReach, FollowUpStatus::Declined, FollowUpStatus::Closed])
        ->and(array_map(fn (FollowUpStatus $s): string => $s->label(), FollowUpStatus::cases()))
        ->toBe(['Needs Follow-Up', 'Attempted Contact', 'Reached', 'Could Not Reach', 'Declined', 'Closed']);
});

test('a move the rules refuse changes nothing', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    followUps()->update($followUp, FollowUpStatus::Declined, 'Changed their mind.', $admin);

    expect(fn () => followUps()->update($followUp, FollowUpStatus::Reached, 'Called back after all.', $admin))
        ->toThrow(ValidationException::class);

    expect($followUp->refresh()->Status)->toBe(FollowUpStatus::Declined)
        ->and($followUp->entries)->toHaveCount(1);
});

test('admins work every kind, secretaries resource requests only, police nothing', function () {
    $people = [
        'admin' => staffUser(UserRole::Admin),
        'secretary' => staffUser(UserRole::Secretary),
        'police admin' => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Follow-up PD 2')),
        'officer' => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Follow-up PD 3')),
        'inactive admin' => staffUser(UserRole::Admin, isActive: false),
    ];
    $workable = [
        'admin' => FollowUpKind::cases(),
        'secretary' => [FollowUpKind::ResourceRequest],
        'police admin' => [],
        'officer' => [],
        'inactive admin' => [],
    ];

    foreach (FollowUpKind::cases() as $kind) {
        $followUp = pestFollowUp($kind);

        foreach ($people as $who => $user) {
            $may = in_array($kind, $workable[$who], true);

            expect(FollowUp::visibleTo($user)->whereKey($followUp->getKey())->exists())
                ->toBe($may, "{$who} seeing a {$kind->label()}");

            $attempt = fn () => followUps()->update($followUp, null, 'A note.', $user);
            $may ? $attempt() : expect($attempt)->toThrow(AuthorizationException::class);
        }
    }
});

test('the history can\'t be changed or removed', function () {
    $admin = staffUser(UserRole::Admin);
    $entry = followUps()->update(pestFollowUp(FollowUpKind::CivilianAssessment), null, 'First note.', $admin);

    expect(fn () => $entry->update(['Note' => 'Rewritten.']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
