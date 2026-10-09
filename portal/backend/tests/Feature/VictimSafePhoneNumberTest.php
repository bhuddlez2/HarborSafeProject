<?php

use App\Enums\UserRole;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
The victim's safe phone number is required on every assessment, officer and
civilian alike, so the organisation can follow up with the victim
(migration 2026_10_08_120000_require_victim_safe_phone_number). Checked at
each layer that could let an assessment through without one. The wizard's own
required marker is asserted in Staff/RequirementMarkersTest.
*/

afterEach(fn () => cleanupStaffData());

test('both assessment tables refuse a missing phone number', function (string $table) {
    $column = collect(Schema::connection('Portal')->getColumns($table))
        ->firstWhere('name', 'VictimSafePhoneNumber');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeFalse();
})->with(['law_enforcement_assessment', '_private_assessment']);

test('the civilian API rejects an assessment without a phone number', function () {
    // Public endpoint - the anonymous civilian flow posts here. Rejected at
    // validation, so nothing is written.
    $this->postJson('/api/private-assessments', [
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
        'VictimFirstName' => 'Test',
        'VictimLastName' => 'Victim',
        'VictimSex' => 'F',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('VictimSafePhoneNumber');
});

test('an officer cannot clear the phone number when editing', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Phone PD'));
    $assessment = pestOfficerAssessment($officer, 'Phone');

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimSafePhoneNumber' => '',
            'reason' => 'Removing the number',
        ])
        ->assertHasActionErrors(['VictimSafePhoneNumber' => 'required']);

    expect($assessment->fresh()->VictimSafePhoneNumber)->toBe('423-555-0100');
});
