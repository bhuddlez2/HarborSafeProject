<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use App\Support\AssessmentDraft;
use Livewire\Livewire;

/*
An unfinished officer assessment kept through the idle logout
(App\Support\AssessmentDraft): saved, encrypted, in the browser tab, and
offered back after signing in again. The same for officers and for police
admins submitting their own.

The saving, encrypting and the Resume / Discard choice happen in the browser
(new-assessment/draft.blade.php); these tests pin what the server decides:
who gets which key, what a resumed form may put back into the wizard, and
that Submit still checks everything.
*/

afterEach(fn () => cleanupStaffData());

// The two roles that use the wizard, each with an agency.
dataset('wizard users', [
    'officer' => [UserRole::LawEnforcement],
    'police admin' => [UserRole::PoliceAdmin],
]);

function draftUser(UserRole $role)
{
    return pestAgencyMember($role, pestAgency('Draft PD'));
}

function savedDraft(array $data = [], array $extra = []): array
{
    return [
        'data' => [
            'VictimFirstName' => 'Draft',
            'VictimLastName' => 'Victim',
            'VictimDOB' => '01/01/1990',
            'VictimSex' => 'F',
            'VictimSafePhoneNumber' => '(423)555-0150',
            ...$data,
        ],
        'step' => 1,
        'questionIndex' => 0,
        'reviewReached' => false,
        ...$extra,
    ];
}

function allAnswers(): array
{
    return collect(range(1, 11))->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => $id === 2])->all();
}

const DRAFT_OFFENDER = [
    'OffenderFirstName' => 'Draft',
    'OffenderLastName' => 'Offender',
    'OffenderSex' => 'M',
    'OffenderVictimRelationship' => 'spouse',
];

test('each person has their own key, the same every time they sign in', function () {
    $officer = draftUser(UserRole::LawEnforcement);
    $other = draftUser(UserRole::LawEnforcement);

    expect(AssessmentDraft::keyFor($officer))->toBe(AssessmentDraft::keyFor($officer->fresh()))
        ->and(AssessmentDraft::keyFor($officer))->not->toBe(AssessmentDraft::keyFor($other))
        // AES-256: 32 bytes, as hex.
        ->and(AssessmentDraft::keyFor($officer))->toMatch('/^[0-9a-f]{64}$/');
});

test('the wizard carries the signed-in person\'s key and nobody else\'s', function (UserRole $role) {
    $user = draftUser($role);
    $other = draftUser($role);

    $this->actingAs($user)
        ->get(NewAssessment::getUrl())
        ->assertOk()
        ->assertSee(AssessmentDraft::keyFor($user))
        ->assertDontSee(AssessmentDraft::keyFor($other))
        ->assertSee('Resume unfinished assessment?');
})->with('wizard users');

test('no other page carries a key, but every page wipes the saved form on sign out', function (UserRole $role) {
    $user = draftUser($role);

    $this->actingAs($user)
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertDontSee(AssessmentDraft::keyFor($user))
        ->assertSee(AssessmentDraft::STORAGE_KEY, escape: false)
        ->assertSee('logoutPath', escape: false);
})->with('wizard users');

test('a resumed form goes back into the wizard, on the step it was saved on', function (UserRole $role) {
    $this->actingAs(draftUser($role));

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft(DRAFT_OFFENDER, ['step' => 2]))
        ->assertReturned(2)
        ->assertSet('data.VictimFirstName', 'Draft')
        ->assertSet('data.VictimSafePhoneNumber', '(423)555-0150')
        ->assertSet('data.OffenderVictimRelationship', 'spouse')
        ->assertSet('reviewReached', false);
})->with('wizard users');

test('only the wizard\'s own fields come back, and the safe number must be confirmed again', function () {
    $this->actingAs(draftUser(UserRole::LawEnforcement));

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([
            'SafePhoneConfirmed' => true,
            'submitted_by' => 1,
            'RiskIndicator1' => 'yes',
            'RiskIndicator2' => true,
            'VictimLastName' => str_repeat('a', 400),
        ]))
        ->assertSet('data.SafePhoneConfirmed', false)
        ->assertSet('data.RiskIndicator1', null)
        ->assertSet('data.RiskIndicator2', true)
        ->assertSet('data.VictimLastName', str_repeat('a', 255))
        ->assertNotSet('data.submitted_by', 1);
});

test('the review reopens only when every question has an answer', function () {
    $this->actingAs(draftUser(UserRole::LawEnforcement));

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([...DRAFT_OFFENDER, 'RiskIndicator1' => true], ['step' => 5, 'reviewReached' => true, 'questionIndex' => 1]))
        ->assertReturned(4)
        ->assertSet('reviewReached', false)
        ->assertSet('questionIndex', 1);

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([...DRAFT_OFFENDER, ...allAnswers()], ['step' => 5]))
        ->assertReturned(5)
        ->assertSet('reviewReached', true);
});

test('out-of-range steps and questions are kept within the wizard', function () {
    $this->actingAs(draftUser(UserRole::LawEnforcement));

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([], ['step' => 99, 'questionIndex' => 40]))
        ->assertReturned(4)
        ->assertSet('questionIndex', 10);

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([], ['step' => -3, 'questionIndex' => -1]))
        ->assertReturned(0)
        ->assertSet('questionIndex', 0);
});

test('nothing is restored from an empty or malformed form', function (mixed $draft) {
    $this->actingAs(draftUser(UserRole::LawEnforcement));

    Livewire::test(NewAssessment::class)
        ->call('restoreDraft', $draft)
        ->assertReturned(null)
        ->assertSet('data.VictimFirstName', null);
})->with([
    'not a form' => ['text'],
    'no fields' => [['data' => [], 'step' => 3]],
    'only unknown fields' => [['data' => ['submitted_by' => 1], 'step' => 3]],
]);

test('a resumed form is submitted as the signed-in person, after the usual checks', function (UserRole $role) {
    $user = draftUser($role);
    $this->actingAs($user);

    $page = Livewire::test(NewAssessment::class)
        ->call('restoreDraft', savedDraft([...DRAFT_OFFENDER, ...allAnswers()], ['step' => 5]))
        ->call('create')
        ->assertHasFormErrors(['SafePhoneConfirmed' => 'accepted'])
        ->assertNotDispatched('hs-assessment-draft-clear');

    expect(LawEnforcementAssessment::where('submitted_by', $user->id)->exists())->toBeFalse();

    $page->set('data.SafePhoneConfirmed', true)
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true)
        // The browser wipes the saved form on this.
        ->assertDispatched('hs-assessment-draft-clear');

    expect(LawEnforcementAssessment::where('submitted_by', $user->id)->value('VictimFirstName'))->toBe('Draft');
})->with('wizard users');

test('a submitted wizard restores nothing', function () {
    $this->actingAs(draftUser(UserRole::LawEnforcement));

    Livewire::test(NewAssessment::class)
        ->set('submitted', true)
        ->call('restoreDraft', savedDraft())
        ->assertReturned(null);
});

test('the idle logout waits for the form to save before reloading, and says it is kept', function () {
    $this->actingAs(draftUser(UserRole::LawEnforcement))
        ->get(NewAssessment::getUrl())
        ->assertOk()
        // The idle logout announces the reload and waits; the wizard's own
        // script (shipped HTML-escaped by Livewire) saves on it.
        ->assertSee("new CustomEvent('hs-before-idle-reload'", escape: false)
        ->assertSee("window.addEventListener('hs-before-idle-reload'")
        ->assertSee('Your unfinished assessment is kept in this browser tab.');
});
