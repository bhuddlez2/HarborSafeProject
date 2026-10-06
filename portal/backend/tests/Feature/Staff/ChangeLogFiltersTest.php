<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use App\Services\AssessmentEditor;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
App\Filament\Tables\ChangeLogFilters: the Change log's filter card, built from
AssessmentFilters' helpers so it behaves like the assessment tables' - deferred
until Apply, resettable, kept in the session. The table lists assessment_edits
rows, so each filter covers detail and answer changes alike. Options and
results only ever come from AssessmentEdit::visibleTo(), so a police admin is
never offered or shown another agency's officers, edits or agency
(Filament_CMS_Design.md section 5, rule 5).

Tests that assert on unfiltered or counted rows act as police admin A, whose
scope is only this file's agency; an admin's view also holds whatever edits
the local database already has.
*/

afterEach(fn () => cleanupStaffData());

// An edit through the one write path. Fails loudly if it records nothing.
function pestEdit(LawEnforcementAssessment $assessment, User $editor, array $details = [], array $answers = []): AssessmentEdit
{
    $edit = AssessmentEditor::apply($assessment, $details, $answers, 'Pest change log filters', $editor);

    expect($edit)->toBeInstanceOf(AssessmentEdit::class);

    return $edit;
}

// Moves an edit's EditedAt. Query builder, because the log models are
// append-only (AppendOnly).
function pestEditedOn(AssessmentEdit $edit, string $date): AssessmentEdit
{
    DB::connection('Portal')->table('assessment_edits')
        ->where('EditID', $edit->getKey())
        ->update(['EditedAt' => $date.' 10:00:00']);

    return $edit;
}

// pestTwoAgencies(), plus a second officer in agency A, and four edits:
//   editA     - officer A on their own record, a detail change, January
//   editA2    - officer A2 on their own record, an answer-only change, March
//   editCross - officer A2 on officer A's record, an answer change, June
//   editB     - officer B on their own record, a detail change, June
// Real edits are always by the submitting officer (the policy allows no one
// else); editCross exists only so Changed by and Original submitter can be
// told apart. AssessmentEditor itself does not check the policy.
function pestChangeLogWorld(): object
{
    $w = pestTwoAgencies();

    $w->officerA2 = pestAgencyMember(UserRole::LawEnforcement, $w->officerA->lawEnforcementAgent->agency);
    $w->assessmentA2 = pestOfficerAssessment($w->officerA2, 'VictimA2');

    $w->editA = pestEditedOn(pestEdit($w->assessmentA, $w->officerA, ['OffenderVictimRelationship' => 'Spouse']), '2026-01-15');
    $w->editA2 = pestEditedOn(pestEdit($w->assessmentA2, $w->officerA2, answers: ['RiskIndicator2' => true]), '2026-03-15');
    $w->editCross = pestEditedOn(pestEdit($w->assessmentA, $w->officerA2, answers: ['RiskIndicator3' => true]), '2026-06-15');
    $w->editB = pestEditedOn(pestEdit($w->assessmentB, $w->officerB, ['OffenderVictimRelationship' => 'Spouse']), '2026-06-15');

    $w->visibleToA = [$w->editA, $w->editA2, $w->editCross];

    return $w;
}

test('each role is offered exactly its filters, agency to admins only', function (string $role, array $expected) {
    $w = pestChangeLogWorld();
    $this->actingAs($role === 'admin' ? staffUser(UserRole::Admin) : $w->policeAdminA);

    expect(array_keys(Livewire::test(ListAssessmentEdits::class)->instance()->getTable()->getFilters()))->toBe($expected);
})->with([
    'admin' => ['admin', [
        'changed_by', 'submitted_by', 'date_changed', 'agency',
        'victim_first_name', 'victim_last_name', 'offender_first_name', 'offender_last_name',
    ]],
    'police admin' => ['police admin', [
        'changed_by', 'submitted_by', 'date_changed',
        'victim_first_name', 'victim_last_name', 'offender_first_name', 'offender_last_name',
    ]],
]);

test('changed by returns that user\'s edits, detail and answer changes alike', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'changed_by', $w->officerA2->getKey())
        ->assertCanSeeTableRecords([$w->editA2, $w->editCross])
        ->assertCountTableRecords(2);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'changed_by', $w->officerA->getKey())
        ->assertCanSeeTableRecords([$w->editA])
        ->assertCountTableRecords(1);
});

test('original submitter matches the assessment\'s submitter, not the editor', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'submitted_by', $w->officerA->getKey())
        ->assertCanSeeTableRecords([$w->editA, $w->editCross])
        ->assertCountTableRecords(2);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'submitted_by', $w->officerA2->getKey())
        ->assertCanSeeTableRecords([$w->editA2])
        ->assertCountTableRecords(1);
});

test('the date changed range returns only edits inside it, with chips', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    $list = Livewire::test(ListAssessmentEdits::class)
        ->set('tableDeferredFilters.date_changed.from', '2026-03-01')
        ->set('tableDeferredFilters.date_changed.until', '2026-03-31')
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->editA2])
        ->assertCountTableRecords(1);

    $labels = collect($list->instance()->getTable()->getFilterIndicators())
        ->map(fn ($indicator): string => (string) $indicator->getLabel())
        ->all();

    expect($labels)->toContain('Changed from 2026-03-01', 'Changed until 2026-03-31');
});

test('an admin is offered every agency\'s people and agencies, and the agency filter narrows', function () {
    $w = pestChangeLogWorld();
    $this->actingAs(staffUser(UserRole::Admin));

    $list = Livewire::test(ListAssessmentEdits::class)->assertTableFilterVisible('agency');

    foreach (['changed_by', 'submitted_by'] as $filter) {
        expect(array_keys(pestFilterSelect($list, $filter)->getSearchResults('Pest')))
            ->toContain($w->officerA->getKey(), $w->officerB->getKey());
    }

    expect(array_keys(pestFilterSelect($list, 'agency')->getSearchResults(STAFF_TEST_PREFIX)))
        ->toContain($w->officerA->agencyId(), $w->officerB->agencyId());

    pestApplyFilter($list, 'agency', $w->officerA->agencyId())
        ->assertCanSeeTableRecords($w->visibleToA)
        ->assertCanNotSeeTableRecords([$w->editB])
        ->assertCountTableRecords(3);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'agency', $w->officerB->agencyId())
        ->assertCanSeeTableRecords([$w->editB])
        ->assertCountTableRecords(1);
});

test('a police admin is offered only their own agency\'s people in both dropdowns', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    $list = Livewire::test(ListAssessmentEdits::class);

    // Every test user shares the first name staffUser() gives it, so compare by key.
    foreach (['changed_by', 'submitted_by'] as $filter) {
        $field = pestFilterSelect($list, $filter);

        foreach ([array_keys($field->getOptions()), array_keys($field->getSearchResults('Pest'))] as $options) {
            expect($options)
                ->toContain($w->officerA->getKey(), $w->officerA2->getKey())
                ->not->toContain($w->officerB->getKey())
                ->not->toContain($w->policeAdminA->getKey())
                ->not->toContain($w->policeAdminB->getKey());
        }
    }
});

test('a police admin forcing another agency\'s people gets no rows', function (string $filter) {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->set("tableFilters.{$filter}.value", $w->officerB->getKey())
        ->assertCanNotSeeTableRecords([$w->editB])
        ->assertCountTableRecords(0);
})->with(['changed_by', 'submitted_by']);

test('a police admin has no agency filter, and forcing one cannot widen the scope', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->assertTableFilterHidden('agency')
        ->set('tableFilters.agency.value', $w->officerB->agencyId())
        ->assertCanSeeTableRecords($w->visibleToA)
        ->assertCanNotSeeTableRecords([$w->editB])
        ->assertCountTableRecords(3);
});

test('filters do nothing until Apply', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->set('tableDeferredFilters.date_changed.from', '2026-06-01')
        ->set('tableDeferredFilters.changed_by.value', $w->officerA2->getKey())
        ->assertCanSeeTableRecords($w->visibleToA)
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->editCross])
        ->assertCountTableRecords(1);
});

test('reset clears applied filters', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->set('tableDeferredFilters.date_changed.from', '2026-06-01')
        ->call('applyTableFilters')
        ->assertCanNotSeeTableRecords([$w->editA])
        ->resetTableFilters()
        ->assertSet('tableFilters.date_changed.from', null)
        ->assertCanSeeTableRecords($w->visibleToA);
});

test('applied filters survive leaving the page and coming back', function () {
    $w = pestChangeLogWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->set('tableDeferredFilters.date_changed.from', '2026-06-01')
        ->call('applyTableFilters');

    Livewire::test(ListAssessmentEdits::class)
        ->assertSet('tableFilters.date_changed.from', '2026-06-01')
        ->assertCanSeeTableRecords([$w->editCross])
        ->assertCanNotSeeTableRecords([$w->editA, $w->editA2]);
});

// pestTwoAgencies(), plus edited records by officer A built to tell the name
// filters apart - the same names AssessmentFiltersTest uses. Each edit changes
// something other than a name. 'renamed' is the exception: its logged
// PreviousValue is 'Quorvath' in every name column, a name the assessment no
// longer has.
function pestNamedEdits(): object
{
    $w = pestTwoAgencies();

    $names = fn (string $name): array => [
        'VictimFirstName' => $name,
        'VictimLastName' => $name,
        'OffenderFirstName' => $name,
        'OffenderLastName' => $name,
    ];

    $edited = fn (LawEnforcementAssessment $assessment): AssessmentEdit => pestEdit($assessment, $w->officerA, ['OffenderVictimRelationship' => 'Spouse']);

    $w->alpha = $edited(pestOfficerAssessment($w->officerA, 'alpha', [
        'VictimFirstName' => 'Zephyrine',
        'VictimLastName' => 'Quillon',
        'OffenderFirstName' => 'Barnaby',
        'OffenderLastName' => 'Thistlewood',
    ]));
    $w->annPercent = $edited(pestOfficerAssessment($w->officerA, 'beta', $names('Ann%Lee')));
    $w->annX = $edited(pestOfficerAssessment($w->officerA, 'gamma', $names('AnnXLee')));
    $w->mcUnderscore = $edited(pestOfficerAssessment($w->officerA, 'delta', $names('Mc_Neil')));
    $w->mcO = $edited(pestOfficerAssessment($w->officerA, 'epsilon', $names('McONeil')));
    $w->renamed = pestEdit(pestOfficerAssessment($w->officerA, 'zeta', $names('Quorvath')), $w->officerA, $names('Plainfield'));
    $w->editB = pestEdit($w->assessmentB, $w->officerB, ['OffenderVictimRelationship' => 'Spouse']);

    return $w;
}

dataset('change log name filters', [
    'victim first name' => 'victim_first_name',
    'victim last name' => 'victim_last_name',
    'offender first name' => 'offender_first_name',
    'offender last name' => 'offender_last_name',
]);

test('each name filter matches the assessment partially and case-insensitively', function (string $filter, string $term) {
    $w = pestNamedEdits();
    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), $filter, $term)
        ->assertCanSeeTableRecords([$w->alpha])
        ->assertCountTableRecords(1);
})->with([
    'victim first name' => ['victim_first_name', 'EPHYR'],
    'victim last name' => ['victim_last_name', 'uILLo'],
    'offender first name' => ['offender_first_name', 'ARNAB'],
    'offender last name' => ['offender_last_name', 'tlewOO'],
]);

test('% and _ in a name filter match literally', function (string $filter) {
    $w = pestNamedEdits();
    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), $filter, 'n%L')
        ->assertCanSeeTableRecords([$w->annPercent])
        ->assertCountTableRecords(1);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), $filter, 'c_N')
        ->assertCanSeeTableRecords([$w->mcUnderscore])
        ->assertCountTableRecords(1);
})->with('change log name filters');

test('name filters match the assessment as it is now, never a logged value', function (string $filter) {
    $w = pestNamedEdits();
    $this->actingAs($w->policeAdminA);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), $filter, 'quorv')
        ->assertCountTableRecords(0);

    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), $filter, 'PLAINF')
        ->assertCanSeeTableRecords([$w->renamed])
        ->assertCountTableRecords(1);
})->with('change log name filters');

test('a police admin filtering on a name from another agency gets no rows', function () {
    $w = pestNamedEdits();
    $otherAgencysVictim = $w->assessmentB->VictimFirstName;

    $this->actingAs($w->policeAdminA);
    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'victim_first_name', $otherAgencysVictim)
        ->assertCountTableRecords(0);

    // Not vacuous: the same filter finds the edit for an admin.
    $this->actingAs(staffUser(UserRole::Admin));
    pestApplyFilter(Livewire::test(ListAssessmentEdits::class), 'victim_first_name', $otherAgencysVictim)
        ->assertCanSeeTableRecords([$w->editB]);
});
