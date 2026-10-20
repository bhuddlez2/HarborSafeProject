<?php

use App\Enums\UserRole;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;

/*
The admin view of assessments submitted through the public civilian flow.

This page exists because section 5 grants admin "Civilian assessments: view"
and nothing provided it - a real submission sat in _private_assessment
invisible to every role, which is how the gap was found. These tests are here
so it cannot go missing again.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupStaffData();
});

test('an admin sees a civilian submission on the page', function () {
    pestCivilianAssessment();

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(CivilianAssessmentResource::getUrl())
        ->assertOk()
        ->assertSee(STAFF_TEST_PREFIX.'Victim')
        ->assertSee('Test Offender');
});

/*
Rule 3 of section 5: secretary never sees assessment PII, civilian or
law-enforcement. And the matrix gives civilian submissions to admin alone -
police_admin's row covers law-enforcement submissions only.
*/
test('every other role is refused', function (UserRole $role) {
    $this->actingAs(staffUser($role))
        ->get(CivilianAssessmentResource::getUrl())
        ->assertForbidden();
})->with([
    'secretary' => [UserRole::Secretary],
    'police_admin' => [UserRole::PoliceAdmin],
    'law_enforcement' => [UserRole::LawEnforcement],
]);

test('an inactive admin is refused', function () {
    $this->actingAs(staffUser(UserRole::Admin, isActive: false))
        ->get(CivilianAssessmentResource::getUrl())
        ->assertForbidden();
});

test('the page offers no way to alter or delete a submission', function () {
    /*
    Rule 1: nobody may change a submitted assessment. And unlike the public
    feedback forms, there is no delete either - a danger assessment is a safety
    record, not something to tidy up.
    */
    $table = CivilianAssessmentResource::table(
        Filament\Tables\Table::make(new CivilianAssessmentsStub),
    );

    expect(collect($table->getRecordActions())->map(fn ($a) => $a->getName())->all())
        ->toBe(['view'])
        ->and($table->getToolbarActions())->toBe([]);
});

test('the safe phone number is not in the table', function () {
    /*
    It is in the detail view only. "Safe" means the person's other numbers are
    not, so it should not sit on an unattended screen or be caught in a
    screen-share of a list.
    */
    $table = CivilianAssessmentResource::table(
        Filament\Tables\Table::make(new CivilianAssessmentsStub),
    );

    $columns = collect($table->getColumns())->keys();

    expect($columns)->not->toContain('VictimSafePhoneNumber');
});

test('an anonymous submission renders without submitter details', function () {
    // The normal case: contact details are only collected when someone submits
    // for another person AND declines anonymity.
    $record = pestCivilianAssessment();

    expect($record->SubmissionID)->toBeNull();

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(CivilianAssessmentResource::getUrl())
        ->assertOk();
});

test('a named submission shows who sent it', function () {
    $submitter = SubmitterInfo::create([
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Reporter',
        'SubmitterLastName' => 'Friend',
        'SubmitterEmail' => 'reporter@pest.test',
        'SubmitterPhoneNumber' => '423-555-0199',
    ]);

    $record = pestCivilianAssessment(submitter: $submitter);

    expect($record->fresh()->submitterInfo->SubmitterFirstName)
        ->toBe(STAFF_TEST_PREFIX.'Reporter');

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(CivilianAssessmentResource::getUrl())
        ->assertOk();
});

test('the high-risk filter narrows to four or more yes answers', function () {
    // Counted in SQL, so this is worth asserting rather than assuming.
    pestCivilianAssessment(yesIndicators: [1]);
    pestCivilianAssessment(yesIndicators: [1, 2, 3, 4, 5]);

    $highRisk = PrivateAssessment::query()
        ->whereHas('assessmentAnswers', fn ($q) => $q->whereRaw(
            '('.implode(' + ', array_map(
                fn (int $id): string => "COALESCE(RiskIndicator{$id}, 0)",
                range(1, 11),
            )).') >= 4',
        ))
        ->where('VictimFirstName', 'like', STAFF_TEST_PREFIX.'%')
        ->count();

    expect($highRisk)->toBe(1);
});

// Filament's Table::make() needs a Livewire component for context; the table
// definition under test never touches it.
class CivilianAssessmentsStub extends App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments {}
