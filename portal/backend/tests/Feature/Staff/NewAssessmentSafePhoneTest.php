<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use Livewire\Livewire;

/*
The officer wizard's second check on the safe phone number: a checkbox on the
Review & Submit step confirming the number is safe to contact. Enforced on the
server (create() validates it), not just by the disabled Submit button, and
cleared whenever the number changes.
*/

afterEach(fn () => cleanupStaffData());

function completedWizardData(array $overrides = []): array
{
    return [
        'VictimFirstName' => 'Safe',
        'VictimLastName' => 'Check',
        'VictimDOB' => '01/01/1990',
        'VictimSex' => 'F',
        'VictimSafePhoneNumber' => '423-555-0150',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
        ...collect(range(1, 11))->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => $id === 1])->all(),
        ...$overrides,
    ];
}

test('an assessment is not saved until the safe number is confirmed', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD'));
    $this->actingAs($officer);

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData())
        ->call('create')
        ->assertHasFormErrors(['SafePhoneConfirmed' => 'accepted']);

    expect(LawEnforcementAssessment::where('submitted_by', $officer->id)->exists())->toBeFalse();
});

test('a confirmed assessment is saved', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD'));
    $this->actingAs($officer);

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData(['SafePhoneConfirmed' => true]))
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    expect(LawEnforcementAssessment::where('submitted_by', $officer->id)->value('VictimSafePhoneNumber'))
        // Stored in the one format, whatever was typed (App\Support\PhoneNumber).
        ->toBe('(423)555-0150');
});

test('the submitted screen lists every hotline as text and as a working link', function () {
    // Team plan task 12: Harbor Safe call and text, the national DV hotline
    // and 988, each shown and tappable. No "call 911" line (item 7): the
    // people on this screen are the police.
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD')));

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData(['SafePhoneConfirmed' => true]))
        ->call('create')
        ->assertSee('Assessment submitted')
        ->assertDontSeeHtml('href="tel:911"')
        ->assertDontSee('call 911')
        ->assertSee('(423) 476-3886')
        ->assertSeeHtml('href="tel:423-476-3886"')
        ->assertSee('(423) 715-9614')
        ->assertSeeHtml('href="sms:423-715-9614"')
        ->assertSee('1-800-799-7233')
        ->assertSeeHtml('href="tel:18007997233"')
        ->assertSee('988')
        ->assertSeeHtml('href="tel:988"');
});

test('the submitted screen has a Back to Home button', function () {
    // Team plan item 8: beside Start new assessment, straight to the Home page.
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD')));

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData(['SafePhoneConfirmed' => true]))
        ->call('create')
        ->assertSeeInOrder(['Start new assessment', 'Back to Home'])
        ->assertSeeHtml('href="'.e(App\Filament\Pages\Police::getUrl()).'"');
});

test('changing the number clears the confirmation', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD')));

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData(['SafePhoneConfirmed' => true]))
        ->assertSet('data.SafePhoneConfirmed', true)
        ->set('data.VictimSafePhoneNumber', '423-555-0199')
        ->assertSet('data.SafePhoneConfirmed', false);
});

test('the review screen shows every detail and every answer', function () {
    // Team plan task 23. Question 11 appears only on the review screen (the
    // questions step shows one question at a time, starting at 1).
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD')));

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData(['OffenderDOB' => '12/25/1985']))
        ->assertSee('Review & Submit')
        ->assertSee(['Safe Check', 'Female', 'Jan 1, 1990', '423-555-0150'])
        ->assertSee(['Test Offender', 'Male', 'Dec 25, 1985', 'Spouse'])
        ->assertSee(NewAssessment::QUESTIONS[11])
        // completedWizardData() answers Yes to question 1 only.
        ->assertSee('1 of 11 yes')
        ->assertDontSee('NOT ANSWERED');
});

test('the confirmation names the number being confirmed', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Safe Phone PD')));

    Livewire::test(NewAssessment::class)
        ->fillForm(completedWizardData())
        ->assertSchemaComponentExists('SafePhoneConfirmed', checkComponentUsing: fn ($field): bool => str_contains((string) $field->getLabel(), '423-555-0150'));
});
