<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\LawEnforcementAssessment;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
App\Filament\Tables\AssessmentFilters: the one filter setup shared by My
Assessments and Assessment review. Filters are deferred (nothing applies until
Apply), resettable and kept in the session, and they only ever narrow a
screen's own visibleTo()-scoped query. Their dropdown options are scoped the
same way, so a police admin is never offered another agency's officers or
agencies (Filament_CMS_Design.md section 5, rule 5).

Filters shared by both tables run against both, through the 'assessment
tables' dataset. Deferral is tested through tableDeferredFilters and
applyTableFilters rather than filterTable(), which writes the applied state
directly and so would skip the very thing under test.
*/

afterEach(fn () => cleanupStaffData());

// Moves an assessment's DateCreated. Query builder, because the model refuses
// any update outside AssessmentEditor.
function pestSubmittedOn(LawEnforcementAssessment $assessment, string $date): LawEnforcementAssessment
{
    DB::connection('Portal')->table('law_enforcement_assessment')
        ->where('DocumentID', $assessment->getKey())
        ->update(['DateCreated' => $date.' 10:00:00']);

    return $assessment;
}

// pestTwoAgencies(), plus two older assessments by officer A, so officer A
// and police admin A each see three rows spread over January, March and June.
function pestDatedAssessments(): object
{
    $w = pestTwoAgencies();

    $w->january = pestSubmittedOn(pestOfficerAssessment($w->officerA, 'January'), '2026-01-15');
    $w->march = pestSubmittedOn(pestOfficerAssessment($w->officerA, 'March'), '2026-03-15');
    $w->june = pestSubmittedOn($w->assessmentA, '2026-06-15');
    pestSubmittedOn($w->assessmentB, '2026-06-15');

    return $w;
}

// One of the table's filter dropdowns, found by its filter name.
function pestFilterSelect(Testable $list, string $filter): Select
{
    return $list->instance()
        ->getTableFiltersForm()
        ->getComponent(
            fn ($component): bool => $component instanceof Select
                && str_ends_with($component->getStatePath(), "{$filter}.value"),
            withHidden: true,
        );
}

dataset('assessment tables', [
    'My Assessments' => [ListLawEnforcementAssessments::class, 'officerA'],
    'Assessment review' => [ListAssessmentReview::class, 'policeAdminA'],
]);

test('a date filter does nothing until Apply', function (string $page, string $viewer) {
    $w = pestDatedAssessments();
    $this->actingAs($w->{$viewer});

    Livewire::test($page)
        ->assertCanSeeTableRecords([$w->january, $w->march, $w->june])
        ->set('tableDeferredFilters.date_submitted.from', '2026-06-01')
        ->assertCanSeeTableRecords([$w->january, $w->march, $w->june])
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->june])
        ->assertCanNotSeeTableRecords([$w->january, $w->march]);
})->with('assessment tables');

test('the date range returns only rows inside it', function (string $page, string $viewer) {
    $w = pestDatedAssessments();
    $this->actingAs($w->{$viewer});

    Livewire::test($page)
        ->set('tableDeferredFilters.date_submitted.from', '2026-03-01')
        ->set('tableDeferredFilters.date_submitted.until', '2026-03-31')
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->march])
        ->assertCanNotSeeTableRecords([$w->january, $w->june, $w->assessmentB]);
})->with('assessment tables');

test('reset clears applied filters', function (string $page, string $viewer) {
    $w = pestDatedAssessments();
    $this->actingAs($w->{$viewer});

    Livewire::test($page)
        ->set('tableDeferredFilters.date_submitted.from', '2026-06-01')
        ->call('applyTableFilters')
        ->assertCanNotSeeTableRecords([$w->january])
        ->resetTableFilters()
        ->assertSet('tableFilters.date_submitted.from', null)
        ->assertCanSeeTableRecords([$w->january, $w->march, $w->june]);
})->with('assessment tables');

test('applied filters survive leaving the page and coming back', function (string $page, string $viewer) {
    $w = pestDatedAssessments();
    $this->actingAs($w->{$viewer});

    Livewire::test($page)
        ->set('tableDeferredFilters.date_submitted.from', '2026-06-01')
        ->call('applyTableFilters');

    Livewire::test($page)
        ->assertSet('tableFilters.date_submitted.from', '2026-06-01')
        ->assertCanSeeTableRecords([$w->june])
        ->assertCanNotSeeTableRecords([$w->january, $w->march]);
})->with('assessment tables');

test('the officer filter on Assessment review does nothing until Apply', function () {
    $w = pestDatedAssessments();
    $officerA2 = pestAgencyMember(UserRole::LawEnforcement, $w->officerA->lawEnforcementAgent->agency);
    $other = pestOfficerAssessment($officerA2, 'OtherOfficer');

    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentReview::class)
        ->set('tableDeferredFilters.submitted_by.value', $officerA2->getKey())
        ->assertCanSeeTableRecords([$w->june, $other])
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$w->january, $w->march, $w->june]);
});

test('a police admin is offered only their own agency\'s officers', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    $field = pestFilterSelect(Livewire::test(ListAssessmentReview::class), 'submitted_by');

    // Both officers share the name staffUser() gives them, so compare by key.
    foreach ([array_keys($field->getOptions()), array_keys($field->getSearchResults('Pest'))] as $options) {
        expect($options)
            ->toContain($w->officerA->getKey())
            ->not->toContain($w->officerB->getKey())
            ->not->toContain($w->policeAdminA->getKey());
    }
});

test('a police admin has no agency filter, and forcing one cannot widen the scope', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentReview::class)
        ->assertTableFilterHidden('agency')
        ->set('tableFilters.agency.value', $w->officerB->agencyId())
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCanNotSeeTableRecords([$w->assessmentB])
        ->set('tableFilters.submitted_by.value', $w->officerB->getKey())
        ->assertCanNotSeeTableRecords([$w->assessmentA, $w->assessmentB]);
});

test('an admin is offered every agency\'s officers and agencies', function () {
    $w = pestTwoAgencies();
    $this->actingAs(staffUser(UserRole::Admin));

    $list = Livewire::test(ListAssessmentReview::class)->assertTableFilterVisible('agency');

    expect(array_keys(pestFilterSelect($list, 'submitted_by')->getSearchResults('Pest')))
        ->toContain($w->officerA->getKey(), $w->officerB->getKey());

    expect(array_keys(pestFilterSelect($list, 'agency')->getSearchResults(STAFF_TEST_PREFIX)))
        ->toContain($w->officerA->agencyId(), $w->officerB->agencyId());

    // And the agency filter narrows, deferred like the rest.
    $list->set('tableDeferredFilters.agency.value', $w->officerA->agencyId())
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCanNotSeeTableRecords([$w->assessmentB]);
});

test('an officer\'s filters only ever return their own rows', function () {
    $w = pestDatedAssessments();
    $this->actingAs($w->officerA);

    $list = Livewire::test(ListLawEnforcementAssessments::class);

    // My Assessments has the date filter only.
    $table = $list->instance()->getTable();
    expect($table->getFilter('submitted_by', withHidden: true))->toBeNull()
        ->and($table->getFilter('agency', withHidden: true))->toBeNull();

    $list->set('tableDeferredFilters.date_submitted.from', '2000-01-01')
        ->set('tableDeferredFilters.date_submitted.until', '2100-12-31')
        // Not a filter on this screen; forced anyway, it must change nothing.
        ->set('tableDeferredFilters.submitted_by.value', $w->officerB->getKey())
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->january, $w->march, $w->june])
        ->assertCanNotSeeTableRecords([$w->assessmentB]);
});

test('Search Records is gone', function () {
    // Route names, not class_exists(): an optimized Composer classmap can
    // still list the deleted class, while Filament discovers pages from disk.
    expect(collect(Route::getRoutes()->getRoutesByName())->keys())
        ->not->toContain('filament.staff.pages.police.search')
        ->not->toContain('filament.staff.pages.search-records');

    foreach ([UserRole::LawEnforcement, UserRole::PoliceAdmin] as $role) {
        $this->actingAs(staffUser($role))->get('/police/search')->assertNotFound();
    }
});
