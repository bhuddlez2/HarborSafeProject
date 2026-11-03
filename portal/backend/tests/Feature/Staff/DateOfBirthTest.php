<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Support\DateOfBirth;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Dates of birth: today back to 125 years ago (App\Support\DateOfBirth), in the
officer wizard, the officer Edit pop-up and the civilian endpoint
(CivilianAssessmentTest covers that one).
*/

afterEach(fn () => cleanupStaffData());

test('the range runs from 125 years ago to today, in Eastern days', function () {
    // 11:30 pm Eastern is already the next day in UTC; today is still the 26th.
    $this->travelTo(Carbon::parse('2026-10-26 23:30', 'America/New_York'));

    expect(DateOfBirth::latest())->toBe('2026-10-26')
        ->and(DateOfBirth::earliest())->toBe('1901-10-26');
});

test('on 29 February the earliest day is the 28th, not 1 March', function () {
    $this->travelTo(Carbon::parse('2028-02-29 12:00', 'America/New_York'));

    expect(DateOfBirth::earliest())->toBe('1903-02-28');
});

// The panel's boxes are typed as MM/DD/YYYY (B28); a Y-m-d day in that form.
$typed = fn (string $day): string => Carbon::parse($day)->format('m/d/Y');

test('the wizard\'s date boxes are typed MM/DD/YYYY with the number keypad', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD')));

    $typedBox = fn (TextInput $field): bool => $field->getMask() === DateOfBirth::MASK
        && $field->getInputMode() === 'numeric';

    Livewire::test(NewAssessment::class)
        ->assertSchemaComponentExists('VictimDOB', checkComponentUsing: $typedBox)
        ->assertSchemaComponentExists('OffenderDOB', checkComponentUsing: $typedBox);
});

test('the wizard refuses a date outside the range', function () use ($typed) {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD')));

    $errors = Livewire::test(NewAssessment::class)
        ->fillForm([
            'VictimDOB' => $typed(Carbon::parse(DateOfBirth::earliest())->subDay()->toDateString()),
            'OffenderDOB' => $typed(Carbon::parse(DateOfBirth::latest())->addDay()->toDateString()),
        ])
        ->call('create')
        ->assertHasFormErrors(['VictimDOB' => 'after_or_equal', 'OffenderDOB' => 'before_or_equal'])
        ->errors();

    expect($errors->first('data.VictimDOB'))->toBe(DateOfBirth::TOO_OLD_MESSAGE)
        ->and($errors->first('data.OffenderDOB'))->toBe(DateOfBirth::FUTURE_MESSAGE);
});

test('the wizard refuses a day that doesn\'t exist and a part-typed date', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD')));

    $errors = Livewire::test(NewAssessment::class)
        ->fillForm(['VictimDOB' => '02/30/1990', 'OffenderDOB' => '03/14'])
        ->call('create')
        ->assertHasFormErrors(['VictimDOB' => 'date_format', 'OffenderDOB' => 'date_format'])
        ->errors();

    expect($errors->first('data.VictimDOB'))->toBe(DateOfBirth::TYPED_MESSAGE);
});

test('a typed date converts to and from the stored Y-m-d', function () {
    expect(DateOfBirth::toIso('03/14/1985'))->toBe('1985-03-14')
        ->and(DateOfBirth::toIso(''))->toBeNull()
        ->and(DateOfBirth::toTyped('1985-03-14'))->toBe('03/14/1985')
        // A resumed draft already holds the typed form.
        ->and(DateOfBirth::toTyped('03/14/1985'))->toBe('03/14/1985');
});

test('the Edit pop-up shows the saved date typed, refuses outside the range and accepts its edges', function () use ($typed) {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD'));
    $assessment = pestOfficerAssessment($officer, 'DOB', ['OffenderVictimRelationship' => 'spouse']);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($assessment))
        ->assertSchemaStateSet(['VictimDOB' => '01/01/1990']);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimDOB' => $typed(Carbon::parse(DateOfBirth::earliest())->subDay()->toDateString()),
            'OffenderDOB' => $typed(Carbon::parse(DateOfBirth::latest())->addDay()->toDateString()),
            'reason' => 'Testing the date range',
        ])
        ->assertHasFormErrors(['VictimDOB' => 'after_or_equal', 'OffenderDOB' => 'before_or_equal']);

    expect(Carbon::parse($assessment->fresh()->VictimDOB)->toDateString())->toBe('1990-01-01');

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimDOB' => $typed(DateOfBirth::earliest()),
            'OffenderDOB' => $typed(DateOfBirth::latest()),
            'reason' => 'Testing the date range',
        ])
        ->assertHasNoFormErrors();

    expect(Carbon::parse($assessment->fresh()->VictimDOB)->toDateString())->toBe(DateOfBirth::earliest())
        ->and(Carbon::parse($assessment->fresh()->OffenderDOB)->toDateString())->toBe(DateOfBirth::latest());
});
