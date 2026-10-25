<?php

use App\Enums\UserRole;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;
use Livewire\Livewire;

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

test('the view shows a named submitter in its own card', function () {
    $submitter = SubmitterInfo::create([
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Reporter',
        'SubmitterLastName' => 'Friend',
        'SubmitterEmail' => 'reporter@pest.test',
        'SubmitterPhoneNumber' => '423-555-0199',
    ]);

    $record = pestCivilianAssessment(submitter: $submitter)->fresh(['assessmentAnswers', 'submitterInfo']);

    $html = view('filament.infolists.components.assessment-record', ['getRecord' => fn () => $record])->render();

    expect($html)
        ->toContain('Submitted by')
        ->toContain('reporter@pest.test')
        ->toContain('Civilian submission')
        ->not->toContain('Anonymous')
        ->not->toContain('Officer:');
});

test('the view marks an anonymous submission and shows no submitter card', function () {
    $record = pestCivilianAssessment()->fresh(['assessmentAnswers', 'submitterInfo']);

    $html = view('filament.infolists.components.assessment-record', ['getRecord' => fn () => $record])->render();

    expect($html)
        ->toContain('Anonymous')
        ->toContain('(423)555-0100')
        ->not->toContain('Submitted by');
});

/*
The PDF route is outside the panel, so canAccess() does not guard it - the
controller looks the record up through PrivateAssessment::visibleTo(), so to
anyone but an admin it does not exist (404). Only refusals are asserted, as in
AssessmentPdfAccessTest: a real download launches headless Chromium.
*/
test('only an active admin may download a civilian assessment', function (UserRole $role, bool $isActive) {
    $record = pestCivilianAssessment();

    $this->actingAs(staffUser($role, isActive: $isActive))
        ->get(route('staff.civilian-assessments.pdf', $record->DocumentID))
        ->assertNotFound();
})->with([
    'secretary' => [UserRole::Secretary, true],
    'police_admin' => [UserRole::PoliceAdmin, true],
    'law_enforcement' => [UserRole::LawEnforcement, true],
]);

test('an inactive admin cannot download a civilian assessment', function () {
    $record = pestCivilianAssessment();

    // EnsureStaffPanelAccess turns deactivated accounts away before the
    // controller runs; either way, no PDF.
    $response = $this->actingAs(staffUser(UserRole::Admin, isActive: false))
        ->get(route('staff.civilian-assessments.pdf', $record->DocumentID));

    expect($response->status())->not->toBe(200);
});

test('an anonymous visitor is sent to sign in for a civilian PDF', function () {
    $record = pestCivilianAssessment();

    $this->get(route('staff.civilian-assessments.pdf', $record->DocumentID))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
});

/*
The high-risk filter (counted in SQL) and the red Yes badge (counted in PHP)
both follow App\Support\HighRisk: a yes to any of Q1-3, or 4 or more yes.
*/
function pestCivilianRiskMix(): object
{
    return (object) [
        'q1Only' => pestCivilianAssessment(yesIndicators: [1]),
        'fourYes' => pestCivilianAssessment(yesIndicators: [4, 5, 6, 7]),
        'threeYes' => pestCivilianAssessment(yesIndicators: [4, 5, 6]),
        'noYes' => pestCivilianAssessment(yesIndicators: []),
    ];
}

test('the high-risk filter follows the high-risk rule', function () {
    $mix = pestCivilianRiskMix();

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListCivilianAssessments::class)
        ->filterTable('high_risk')
        ->assertCanSeeTableRecords([$mix->q1Only, $mix->fourYes])
        ->assertCanNotSeeTableRecords([$mix->threeYes, $mix->noYes]);
});

test('the Yes badge is red when high risk, amber for any other yes', function () {
    $mix = pestCivilianRiskMix();

    $this->actingAs(staffUser(UserRole::Admin));

    $column = Livewire::test(ListCivilianAssessments::class)
        ->instance()->getTable()->getColumn('risk_count');
    $color = fn (PrivateAssessment $record) => $column->record($record->load('assessmentAnswers'))->getColor(null);

    expect($color($mix->q1Only))->toBe('danger')
        ->and($color($mix->fourYes))->toBe('danger')
        ->and($color($mix->threeYes))->toBe('warning')
        ->and($color($mix->noYes))->toBe('gray');
});

// Filament's Table::make() needs a Livewire component for context; the table
// definition under test never touches it.
class CivilianAssessmentsStub extends App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments {}
