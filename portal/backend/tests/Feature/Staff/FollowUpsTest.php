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
        'VictimSafePhoneNumber' => '(423)555-0100',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
    ])->assertCreated();
    $civilian = App\Models\PrivateAssessment::where('VictimFirstName', STAFF_TEST_PREFIX.'Victim')->sole();

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
    followUps()->update($followUp, FollowUpStatus::Reached, 'Spoke with her; referred to shelter.', $admin);
    followUps()->update($followUp, null, 'Checked in a week later.', $admin);

    $entries = $followUp->refresh()->entries;

    expect($followUp->Status)->toBe(FollowUpStatus::Reached)
        ->and($entries)->toHaveCount(4)
        ->and($entries->pluck('Note')->all())->toBe([
            'Tried the number, no answer yet.',
            'Will try again tomorrow.',
            'Spoke with her; referred to shelter.',
            'Checked in a week later.',
        ])
        ->and($entries->map(fn (FollowUpEntry $e): bool => $e->changedStatus())->all())->toBe([false, false, true, false]);
});

test('which moves each kind of form may make', function (FollowUpKind $kind, FollowUpStatus $from, FollowUpStatus $to, bool $allowed) {
    expect($from->canMoveTo($to, $kind))->toBe($allowed);
})->with([
    // From Needs follow-up: anywhere but back to itself.
    'needs -> attempted' => [FollowUpKind::ResourceRequest, FollowUpStatus::NeedsFollowUp, FollowUpStatus::AttemptedContact, true],
    'needs -> reached' => [FollowUpKind::ResourceRequest, FollowUpStatus::NeedsFollowUp, FollowUpStatus::Reached, true],
    'needs -> declined' => [FollowUpKind::LawEnforcementAssessment, FollowUpStatus::NeedsFollowUp, FollowUpStatus::Declined, true],
    // From Attempted contact: on to a final status only.
    'attempted -> could not reach' => [FollowUpKind::CivilianAssessment, FollowUpStatus::AttemptedContact, FollowUpStatus::CouldNotReach, true],
    'attempted -> needs' => [FollowUpKind::CivilianAssessment, FollowUpStatus::AttemptedContact, FollowUpStatus::NeedsFollowUp, false],
    // Final: an assessment may move between finals, a resource request may not.
    'assessment reached -> declined' => [FollowUpKind::CivilianAssessment, FollowUpStatus::Reached, FollowUpStatus::Declined, true],
    'officer assessment could not reach -> reached' => [FollowUpKind::LawEnforcementAssessment, FollowUpStatus::CouldNotReach, FollowUpStatus::Reached, true],
    'assessment reached -> attempted' => [FollowUpKind::CivilianAssessment, FollowUpStatus::Reached, FollowUpStatus::AttemptedContact, false],
    'assessment reached -> needs' => [FollowUpKind::LawEnforcementAssessment, FollowUpStatus::Reached, FollowUpStatus::NeedsFollowUp, false],
    'request reached -> declined' => [FollowUpKind::ResourceRequest, FollowUpStatus::Reached, FollowUpStatus::Declined, false],
    'request declined -> attempted' => [FollowUpKind::ResourceRequest, FollowUpStatus::Declined, FollowUpStatus::AttemptedContact, false],
]);

test('a move the rules refuse changes nothing', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    followUps()->update($followUp, FollowUpStatus::Reached, 'Sent the shelter list.', $admin);

    expect(fn () => followUps()->update($followUp, FollowUpStatus::Declined, 'Changed their mind.', $admin))
        ->toThrow(ValidationException::class);

    expect($followUp->refresh()->Status)->toBe(FollowUpStatus::Reached)
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
