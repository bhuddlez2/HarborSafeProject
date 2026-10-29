<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Support\DateOfBirth;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\DatePicker;
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

// Filament adds a time to each limit (00:00:00 / 23:59:59), so both end days
// stay allowed; compare the days.
$bounded = fn (DatePicker $field): bool => substr($field->getMinDate(), 0, 10) === DateOfBirth::earliest()
    && substr($field->getMaxDate(), 0, 10) === DateOfBirth::latest();

test('the wizard\'s date boxes only offer the range, and refuse outside it', function () use ($bounded) {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD')));

    $tooOld = Carbon::parse(DateOfBirth::earliest())->subDay()->toDateString();
    $future = Carbon::parse(DateOfBirth::latest())->addDay()->toDateString();

    $errors = Livewire::test(NewAssessment::class)
        ->assertSchemaComponentExists('VictimDOB', checkComponentUsing: $bounded)
        ->assertSchemaComponentExists('OffenderDOB', checkComponentUsing: $bounded)
        ->fillForm(['VictimDOB' => $tooOld, 'OffenderDOB' => $future])
        ->call('create')
        ->assertHasFormErrors(['VictimDOB' => 'after_or_equal', 'OffenderDOB' => 'before_or_equal'])
        ->errors();

    expect($errors->first('data.VictimDOB'))->toBe(DateOfBirth::TOO_OLD_MESSAGE)
        ->and($errors->first('data.OffenderDOB'))->toBe(DateOfBirth::FUTURE_MESSAGE);
});

test('the Edit pop-up refuses a date outside the range and accepts its edges', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('DOB PD'));
    $assessment = pestOfficerAssessment($officer, 'DOB', ['OffenderVictimRelationship' => 'spouse']);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimDOB' => Carbon::parse(DateOfBirth::earliest())->subDay()->toDateString(),
            'OffenderDOB' => Carbon::parse(DateOfBirth::latest())->addDay()->toDateString(),
            'reason' => 'Testing the date range',
        ])
        ->assertHasFormErrors(['VictimDOB' => 'after_or_equal', 'OffenderDOB' => 'before_or_equal']);

    expect(Carbon::parse($assessment->fresh()->VictimDOB)->toDateString())->toBe('1990-01-01');

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimDOB' => DateOfBirth::earliest(),
            'OffenderDOB' => DateOfBirth::latest(),
            'reason' => 'Testing the date range',
        ])
        ->assertHasNoFormErrors();

    expect(Carbon::parse($assessment->fresh()->VictimDOB)->toDateString())->toBe(DateOfBirth::earliest())
        ->and(Carbon::parse($assessment->fresh()->OffenderDOB)->toDateString())->toBe(DateOfBirth::latest());
});
