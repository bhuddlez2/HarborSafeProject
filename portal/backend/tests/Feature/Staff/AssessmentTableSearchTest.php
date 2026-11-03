<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\LawEnforcementAssessment;
use Livewire\Livewire;

/*
The three assessment tables search only through their filter card
(App\Filament\Tables\AssessmentFilters): no search bar above the table and no
search box under any column (2026-10-29, replacing #64's search by field).
The filters still narrow each table's own visibleTo()-scoped query, so a
police admin never finds another agency's records and an officer only their
own. AssessmentFiltersTest and CivilianAssessmentFiltersTest cover each
filter in depth.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupStaffData();
});

dataset('assessment tables and viewers', [
    'My Assessments (officer)' => [ListLawEnforcementAssessments::class, fn () => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Search PD'))],
    'Assessment review (police admin)' => [ListAssessmentReview::class, fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Search PD'))],
    'Assessment review (admin)' => [ListAssessmentReview::class, fn () => staffUser(UserRole::Admin)],
    'Civilian assessments (admin)' => [ListCivilianAssessments::class, fn () => staffUser(UserRole::Admin)],
]);

test('has no search bar and no search box under any column', function (string $page, Closure $viewer) {
    $this->actingAs($viewer());

    $list = Livewire::test($page)->assertOk();
    $table = $list->instance()->getTable();

    $searchable = collect($table->getColumns())
        ->filter(fn ($column): bool => $column->isSearchable())
        ->keys()
        ->all();

    expect($table->isSearchable())->toBeFalse()
        ->and($table->isSearchableByColumn())->toBeFalse()
        ->and($searchable)->toBe([]);

    $list->assertDontSeeHtml('wire:model.live.debounce.500ms="tableSearch"')
        ->assertDontSeeHtml('tableColumnSearches.')
        // The filter card is still there.
        ->assertTableFilterExists('date_submitted')
        ->assertTableFilterExists('victim_first_name');
})->with('assessment tables and viewers');

test('the filter card still finds rows, within a police admin\'s agency only', function () {
    $w = pestTwoAgencies();
    $nameA = LawEnforcementAssessment::find($w->assessmentA->getKey())->VictimFirstName;
    $nameB = LawEnforcementAssessment::find($w->assessmentB->getKey())->VictimFirstName;

    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentReview::class), 'victim_first_name', $nameA)
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCountTableRecords(1);

    pestApplyFilter(Livewire::test(ListAssessmentReview::class), 'victim_first_name', $nameB)
        ->assertCountTableRecords(0);

    // Agency B's officer, forced in past the dropdown's own options.
    Livewire::test(ListAssessmentReview::class)
        ->set('tableFilters.submitted_by.value', $w->officerB->getKey())
        ->assertCanNotSeeTableRecords([$w->assessmentB]);
});

test('the filter card still finds rows, within an officer\'s own only', function () {
    $w = pestTwoAgencies();
    $nameA = LawEnforcementAssessment::find($w->assessmentA->getKey())->VictimFirstName;
    $nameB = LawEnforcementAssessment::find($w->assessmentB->getKey())->VictimFirstName;

    $this->actingAs($w->officerA);

    pestApplyFilter(Livewire::test(ListLawEnforcementAssessments::class), 'victim_last_name', 'Doe')
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCanNotSeeTableRecords([$w->assessmentB]);

    pestApplyFilter(Livewire::test(ListLawEnforcementAssessments::class), 'victim_first_name', $nameA)
        ->assertCountTableRecords(1);

    pestApplyFilter(Livewire::test(ListLawEnforcementAssessments::class), 'victim_first_name', $nameB)
        ->assertCountTableRecords(0);
});
