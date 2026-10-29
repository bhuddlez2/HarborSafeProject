<?php

use App\Enums\UserRole;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\PrivateAssessment;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Civilian assessments uses the police tables' filter card
(AssessmentFilters::civilian()): date submitted and victim/offender names,
folded behind the Filters button, deferred until Apply, kept in the session -
plus its own high-risk checkbox. The shared filters'
finer behaviour (wildcards, chips, Reset) is covered against the police tables
in AssessmentFiltersTest; this file checks the civilian screen is wired to it.

The local database may hold real civilian submissions, so these assert on the
test rows only, never on counts.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupStaffData();
});

// Query builder, like the officer version in AssessmentFiltersTest.
function pestCivilianSubmittedOn(PrivateAssessment $assessment, string $date): PrivateAssessment
{
    DB::connection('Portal')->table('_private_assessment')
        ->where('DocumentID', $assessment->getKey())
        ->update(['DateCreated' => $date.' 10:00:00']);

    return $assessment;
}

// Two rows with distinct names, in January and June 2003 (a year nothing
// else uses).
function pestCivilianFilterRows(): object
{
    return (object) [
        'january' => pestCivilianSubmittedOn(pestCivilianAssessment(overrides: [
            'VictimFirstName' => STAFF_TEST_PREFIX.'Zephyrine',
            'VictimLastName' => 'Quillon',
            'OffenderFirstName' => 'Barnaby',
            'OffenderLastName' => 'Thistlewood',
        ]), '2003-01-15'),
        'june' => pestCivilianSubmittedOn(pestCivilianAssessment(overrides: [
            'VictimFirstName' => STAFF_TEST_PREFIX.'Marguerite',
            'VictimLastName' => 'Ostrander',
            'OffenderFirstName' => 'Leopold',
            'OffenderLastName' => 'Fairweather',
        ]), '2003-06-15'),
    ];
}

test('the civilian table offers the police tables\' filters plus high risk', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(array_keys(Livewire::test(ListCivilianAssessments::class)->instance()->getTable()->getFilters()))->toBe([
        'date_submitted',
        'victim_first_name', 'victim_last_name', 'offender_first_name', 'offender_last_name',
        'high_risk',
    ]);
});

test('the civilian filter card is laid out like the police tables\'', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $table = Livewire::test(ListCivilianAssessments::class)->instance()->getTable();

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContentCollapsible)
        ->and($table->hasDeferredFilters())->toBeTrue()
        ->and($table->persistsFiltersInSession())->toBeTrue()
        ->and($table->getFiltersFormColumns())->toBe(3);
});

test('the civilian date filter does nothing until Apply, then keeps rows inside it', function () {
    $rows = pestCivilianFilterRows();
    $this->actingAs(staffUser(UserRole::Admin));

    // Narrowed to this test's two rows first (both victims' first names carry
    // the test prefix): the table shows 10 rows a page, newest first, so the
    // local database's own rows would otherwise push these 2003 rows off page 1.
    pestApplyFilter(Livewire::test(ListCivilianAssessments::class), 'victim_first_name', STAFF_TEST_PREFIX)
        ->set('tableDeferredFilters.date_submitted.from', '2003-06-01')
        ->set('tableDeferredFilters.date_submitted.until', '2003-06-30')
        ->assertCanSeeTableRecords([$rows->january, $rows->june])
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$rows->june])
        ->assertCanNotSeeTableRecords([$rows->january]);
});

test('each civilian name filter matches partially and case-insensitively', function (string $filter, string $term) {
    $rows = pestCivilianFilterRows();
    $this->actingAs(staffUser(UserRole::Admin));

    pestApplyFilter(Livewire::test(ListCivilianAssessments::class), $filter, $term)
        ->assertCanSeeTableRecords([$rows->january])
        ->assertCanNotSeeTableRecords([$rows->june]);
})->with([
    'victim first name' => ['victim_first_name', 'EPHYR'],
    'victim last name' => ['victim_last_name', 'uILLo'],
    'offender first name' => ['offender_first_name', 'ARNAB'],
    'offender last name' => ['offender_last_name', 'tlewOO'],
]);

test('the high-risk checkbox applies through the card too', function () {
    $highRisk = pestCivilianAssessment(yesIndicators: [1]);
    $lowRisk = pestCivilianAssessment(yesIndicators: []);

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListCivilianAssessments::class)
        ->set('tableDeferredFilters.high_risk.isActive', true)
        ->assertCanSeeTableRecords([$lowRisk])
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$highRisk])
        ->assertCanNotSeeTableRecords([$lowRisk]);
});
