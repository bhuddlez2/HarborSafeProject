<?php

use App\Enums\UserRole;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
The Edit pop-up holds an assessment to the standard of the original
submission: every field uses the wizard's rules (App\Filament\Assessments\
AssessmentInputs), every question must stay answered, and a changed safe
phone number must be confirmed as safe, as in the wizard. Nothing that fails
is saved or logged.
*/

afterEach(fn () => cleanupStaffData());

function editStandardsFixture(): object
{
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Edit Standards PD'));
    $assessment = pestOfficerAssessment($officer, 'Standards', ['OffenderVictimRelationship' => 'spouse']);

    return (object) ['officer' => $officer, 'assessment' => $assessment];
}

function editStandardsSave(LawEnforcementAssessment $assessment, array $data)
{
    return Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'reason' => 'Testing the edit standards',
            ...$data,
        ]);
}

test('an edit that breaks a wizard rule is refused and nothing is logged', function (array $data, array $errors) {
    $f = editStandardsFixture();
    $this->actingAs($f->officer);

    editStandardsSave($f->assessment, $data)->assertHasFormErrors($errors);

    expect(AssessmentEdit::where('DocumentID', $f->assessment->getKey())->count())->toBe(0);
})->with([
    'a number in a name' => [['VictimLastName' => 'D0e'], ['VictimLastName' => 'regex']],
    'a symbol in a name' => [['OffenderFirstName' => 'Te$t'], ['OffenderFirstName' => 'regex']],
    'the victim\'s birth date removed' => [['VictimDOB' => null], ['VictimDOB' => 'required']],
    'an unknown sex code' => [['VictimSex' => 'X'], ['VictimSex']],
    'a question left unanswered' => [['RiskIndicator7' => null], ['RiskIndicator7' => 'required']],
    'a short phone number' => [['VictimSafePhoneNumber' => '423-555'], ['VictimSafePhoneNumber']],
]);

test('a changed safe phone number must be confirmed as safe', function () {
    $f = editStandardsFixture();
    $this->actingAs($f->officer);

    editStandardsSave($f->assessment, ['VictimSafePhoneNumber' => '(423)555-0199'])
        ->assertHasFormErrors(['SafePhoneConfirmed' => 'accepted']);

    expect($f->assessment->fresh()->VictimSafePhoneNumber)->toBe('(423)555-0100');

    editStandardsSave($f->assessment, ['VictimSafePhoneNumber' => '(423)555-0199', 'SafePhoneConfirmed' => true])
        ->assertHasNoFormErrors();

    expect($f->assessment->fresh()->VictimSafePhoneNumber)->toBe('(423)555-0199');
});

test('the confirmation is only asked when the number changes', function () {
    $f = editStandardsFixture();
    $this->actingAs($f->officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($f->assessment))
        ->assertFormFieldHidden('SafePhoneConfirmed')
        // The same number, typed differently, is not a change.
        ->fillForm(['VictimSafePhoneNumber' => '423-555-0100'])
        ->assertFormFieldHidden('SafePhoneConfirmed')
        ->fillForm(['VictimSafePhoneNumber' => '423-555-0199'])
        ->assertFormFieldVisible('SafePhoneConfirmed');

    editStandardsSave($f->assessment, ['VictimLastName' => 'Smith'])->assertHasNoFormErrors();

    expect($f->assessment->fresh()->VictimLastName)->toBe('Smith');
});
