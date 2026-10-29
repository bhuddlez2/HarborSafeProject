<?php

use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use Livewire\Livewire;

/*
Search by field on the two law-enforcement assessment tables (#64): a search
box under Victim (victim name), Offender (offender name or relationship) and,
on Assessment review only, Submitted (the submitting officer), from
App\Filament\Tables\AssessmentColumns::for(). Names match a full "First Last"
or either part, ignoring capitals, with % and _ taken literally. The names
are encrypted, so they are searched the way the existing search does
(App\Support\EncryptedSearch, over the viewer's visibleTo() rows only), and a
search only ever narrows the screen's own scoped query. The safe phone number
is never searchable.
*/

afterEach(fn () => cleanupStaffData());

// pestTwoAgencies() plus named rows: officer A (Alice Ng) has Jane Doe,
// a wildcard-named victim and the default VictimA; officer B (Bob Kerr) has
// VictimB and a Jane Doe of their own, which agency A must never find.
function pestFieldSearchWorld(): object
{
    $w = pestTwoAgencies();

    $w->officerA->update(['first_name' => 'Alice', 'last_name' => 'Ng']);
    $w->officerB->update(['first_name' => 'Bob', 'last_name' => 'Kerr']);

    $w->jane = pestOfficerAssessment($w->officerA, 'x', [
        'VictimFirstName' => 'Jane',
        'VictimLastName' => 'Doe',
        'OffenderFirstName' => 'John',
        'OffenderLastName' => 'Smith',
        'OffenderVictimRelationship' => 'spouse',
    ]);

    $w->wild = pestOfficerAssessment($w->officerA, 'x', [
        'VictimFirstName' => 'Ja%ne',
        'VictimLastName' => 'Do_e',
        'OffenderFirstName' => 'Mark',
        'OffenderLastName' => 'Lee',
        'OffenderVictimRelationship' => 'other',
        'OffenderVictimRelationshipOther' => 'Neighbour',
    ]);

    $w->janeB = pestOfficerAssessment($w->officerB, 'x', [
        'VictimFirstName' => 'Jane',
        'VictimLastName' => 'Doe',
        'OffenderFirstName' => 'John',
        'OffenderLastName' => 'Smith',
        'OffenderVictimRelationship' => 'spouse',
    ]);

    return $w;
}

dataset('field search tables', [
    'My Assessments' => [ListLawEnforcementAssessments::class, 'officerA'],
    'Assessment review' => [ListAssessmentReview::class, 'policeAdminA'],
]);

// The table's rows after searching the given column boxes.
function pestFieldSearch(string $page, array $searches): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test($page)->searchTableColumns($searches);
}

test('the victim box finds a full name, either part, in any capitals', function (string $page, string $viewer) {
    $w = pestFieldSearchWorld();
    $this->actingAs($w->{$viewer});

    foreach (['Jane Doe', 'jane', 'DOE', 'jane   doe', ' Jane Doe '] as $search) {
        pestFieldSearch($page, ['VictimLastName' => $search])
            ->assertCanSeeTableRecords([$w->jane])
            ->assertCanNotSeeTableRecords([$w->janeB, $w->assessmentB]);
    }

    pestFieldSearch($page, ['VictimLastName' => 'Jane Doe'])
        ->assertCanNotSeeTableRecords([$w->assessmentA, $w->wild]);

    // Last name first is not the full name.
    pestFieldSearch($page, ['VictimLastName' => 'Doe Jane'])
        ->assertCountTableRecords(0);
})->with('field search tables');

test('the offender box finds a name or a relationship', function (string $page, string $viewer) {
    $w = pestFieldSearchWorld();
    $this->actingAs($w->{$viewer});

    pestFieldSearch($page, ['OffenderLastName' => 'john smith'])
        ->assertCanSeeTableRecords([$w->jane])
        ->assertCanNotSeeTableRecords([$w->wild, $w->assessmentA, $w->janeB]);

    pestFieldSearch($page, ['OffenderLastName' => 'Spouse'])
        ->assertCanSeeTableRecords([$w->jane])
        ->assertCanNotSeeTableRecords([$w->wild, $w->assessmentA, $w->janeB]);

    pestFieldSearch($page, ['OffenderLastName' => 'neighbour'])
        ->assertCanSeeTableRecords([$w->wild])
        ->assertCanNotSeeTableRecords([$w->jane, $w->assessmentA]);

    pestFieldSearch($page, ['OffenderLastName' => 'Other: Neighbour'])
        ->assertCanSeeTableRecords([$w->wild])
        ->assertCountTableRecords(1);

    // The offender box doesn't search victims, nor the victim box offenders.
    pestFieldSearch($page, ['OffenderLastName' => 'Jane'])->assertCountTableRecords(0);
    pestFieldSearch($page, ['VictimLastName' => 'Smith'])->assertCountTableRecords(0);
})->with('field search tables');

test('boxes combine, each narrowing the other', function (string $page, string $viewer) {
    $w = pestFieldSearchWorld();
    $this->actingAs($w->{$viewer});

    pestFieldSearch($page, ['VictimLastName' => 'Doe', 'OffenderLastName' => 'Spouse'])
        ->assertCanSeeTableRecords([$w->jane])
        ->assertCountTableRecords(1);
})->with('field search tables');

test('% and _ are taken literally', function (string $page, string $viewer) {
    $w = pestFieldSearchWorld();
    $this->actingAs($w->{$viewer});

    pestFieldSearch($page, ['VictimLastName' => '%'])
        ->assertCanSeeTableRecords([$w->wild])
        ->assertCountTableRecords(1);

    pestFieldSearch($page, ['VictimLastName' => 'Do_e'])
        ->assertCanSeeTableRecords([$w->wild])
        ->assertCountTableRecords(1);

    // As wildcards these would find Jane Doe.
    pestFieldSearch($page, ['VictimLastName' => 'J_ne'])->assertCountTableRecords(0);
    pestFieldSearch($page, ['VictimLastName' => 'J%e'])->assertCountTableRecords(0);
})->with('field search tables');

test('Assessment review searches the officer by full name or part; My Assessments has no officer box', function () {
    $w = pestFieldSearchWorld();

    $this->actingAs($w->policeAdminA);

    foreach (['Alice Ng', 'alice', 'NG'] as $search) {
        pestFieldSearch(ListAssessmentReview::class, ['DateCreated' => $search])
            ->assertCanSeeTableRecords([$w->assessmentA, $w->jane, $w->wild])
            ->assertCanNotSeeTableRecords([$w->assessmentB, $w->janeB]);
    }

    pestFieldSearch(ListAssessmentReview::class, ['DateCreated' => 'A%'])->assertCountTableRecords(0);

    // Agency B's officer is never found by agency A's police admin.
    pestFieldSearch(ListAssessmentReview::class, ['DateCreated' => 'Bob Kerr'])->assertCountTableRecords(0);

    $this->actingAs($w->officerA);

    $myTable = Livewire::test(ListLawEnforcementAssessments::class)->instance()->getTable();

    expect($myTable->getColumn('DateCreated')->isIndividuallySearchable())->toBeFalse();
});

test('a police admin and an officer only ever find their own scope', function () {
    $w = pestFieldSearchWorld();

    $this->actingAs($w->policeAdminA);

    pestFieldSearch(ListAssessmentReview::class, ['VictimLastName' => 'VictimB'])->assertCountTableRecords(0);
    pestFieldSearch(ListAssessmentReview::class, ['VictimLastName' => 'Jane Doe'])
        ->assertCanSeeTableRecords([$w->jane])
        ->assertCountTableRecords(1);

    $this->actingAs($w->policeAdminB);

    pestFieldSearch(ListAssessmentReview::class, ['VictimLastName' => 'Jane Doe'])
        ->assertCanSeeTableRecords([$w->janeB])
        ->assertCountTableRecords(1);
    pestFieldSearch(ListAssessmentReview::class, ['DateCreated' => 'Alice'])->assertCountTableRecords(0);

    $this->actingAs($w->officerB);

    pestFieldSearch(ListLawEnforcementAssessments::class, ['VictimLastName' => 'Jane'])
        ->assertCanSeeTableRecords([$w->janeB])
        ->assertCountTableRecords(1);
    pestFieldSearch(ListLawEnforcementAssessments::class, ['VictimLastName' => 'VictimA'])->assertCountTableRecords(0);
});

test('the safe phone number is not searchable', function (string $page, string $viewer) {
    $w = pestFieldSearchWorld();
    $this->actingAs($w->{$viewer});

    $list = Livewire::test($page);
    $table = $list->instance()->getTable();

    expect($table->getColumn('VictimSafePhoneNumber'))->toBeNull();

    // Every assessment here has the safe number 423-555-0100.
    foreach (['423-555-0100', '(423)555-0100', '555-0100'] as $phone) {
        $boxes = collect($table->getColumns())
            ->filter(fn ($column): bool => $column->isIndividuallySearchable())
            ->mapWithKeys(fn ($column): array => [$column->getName() => $phone]);

        foreach ($boxes as $box => $search) {
            pestFieldSearch($page, [$box => $search])->assertCountTableRecords(0);
        }

        Livewire::test($page)->searchTable($phone)->assertCountTableRecords(0);
    }
})->with('field search tables');
