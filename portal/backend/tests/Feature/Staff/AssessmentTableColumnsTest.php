<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Services\AssessmentEditor;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
The law-enforcement assessment tables' columns and row buttons
(App\Filament\Tables\AssessmentColumns, shared by both), and the Change log
button's colour: purple when there is a change log to see, light gray when
there isn't.
*/

afterEach(fn () => cleanupStaffData());

function columnsOf(string $page): array
{
    return collect(Livewire::test($page)->instance()->getTable()->getColumns())
        ->map(fn ($column): string => $column->getLabel())
        ->values()
        ->all();
}

function recordActionsOf(string $page): array
{
    return collect(Livewire::test($page)->instance()->getTable()->getRecordActions())
        ->map(fn ($action): string => $action->getName())
        ->all();
}

test('My Assessments shows the agreed columns, in order', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD')));

    expect(columnsOf(ListLawEnforcementAssessments::class))
        ->toBe(['Submitted', 'Victim', 'Offender', 'Relationship', 'Yes', 'Status']);
});

test('Assessment review shows the same columns, plus who submitted', function () {
    // Officer kept here: reviewers need to see who submitted each one.
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Columns PD')));

    expect(columnsOf(ListAssessmentReview::class))
        ->toBe(['Submitted', 'Officer', 'Victim', 'Offender', 'Relationship', 'Yes', 'Status']);
});

test('the buttons sit on the right: Change log, View, Edit', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD')));
    expect(recordActionsOf(ListLawEnforcementAssessments::class))->toBe(['changeLog', 'view', 'edit']);
});

// The same shared Edit, after View, shown only where the policy allows: a
// police admin on their agency's rows, never an admin.
test('Assessment review has the same buttons, Edit last', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Columns PD')));
    expect(recordActionsOf(ListAssessmentReview::class))->toBe(['changeLog', 'view', 'edit']);
});

test('an admin sees no Edit on Assessment review', function () {
    $w = pestTwoAgencies();
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListAssessmentReview::class)
        ->assertActionHidden(TestAction::make('edit')->table($w->assessmentA));
});

test('relationship is its own column, not a line under the offender', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD'));
    $assessment = pestOfficerAssessment($officer, 'Rel', ['OffenderVictimRelationship' => 'former_dating_partner']);

    $this->actingAs($officer);

    // Shown as the option's label (App\Enums\OffenderRelationship).
    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertTableColumnFormattedStateSet('OffenderVictimRelationship', 'Former Dating Partner', $assessment)
        ->assertTableColumnDoesNotHaveDescription('OffenderLastName', 'Former Dating Partner', $assessment);
});

test('the civilian table calls the same column "Yes" too', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(columnsOf(ListCivilianAssessments::class))->toContain('Yes')
        ->not->toContain('Yes answers');
});

test('the Change log button is purple on an edited record and gray on an unedited one', function (string $page, UserRole $viewerRole) {
    $agency = pestAgency('Columns PD');
    $officer = pestAgencyMember(UserRole::LawEnforcement, $agency);
    $edited = pestOfficerAssessment($officer, 'Edited');
    $untouched = pestOfficerAssessment($officer, 'Untouched');

    AssessmentEditor::apply($edited, ['VictimLastName' => 'Smith'], [], 'Spelling', $officer);

    // The officer sees them on My Assessments, their police admin on review.
    $this->actingAs($viewerRole === UserRole::LawEnforcement ? $officer : pestAgencyMember($viewerRole, $agency));

    Livewire::test($page)
        ->assertActionEnabled(TestAction::make('changeLog')->table($edited))
        ->assertActionHasColor(TestAction::make('changeLog')->table($edited), 'primary')
        ->assertActionDisabled(TestAction::make('changeLog')->table($untouched))
        ->assertActionHasColor(TestAction::make('changeLog')->table($untouched), 'gray');
})->with([
    'My Assessments' => [ListLawEnforcementAssessments::class, UserRole::LawEnforcement],
    'Assessment review' => [ListAssessmentReview::class, UserRole::PoliceAdmin],
]);

// The Yes badge follows App\Support\HighRisk, the rule Analytics counts: red
// for a yes to any of Q1-3 or 4 or more yes, amber for any other yes.
test('the Yes badge is red when high risk, amber for any other yes', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD'));

    $withYes = function (array $yes, string $victim) use ($officer) {
        $record = pestOfficerAssessment($officer, $victim);
        // The answers model refuses update() outside AssessmentEditor.
        Illuminate\Support\Facades\DB::connection('Portal')->table('_assessment_answers')
            ->where('AssessmentDocID', $record->AssessmentDocID)
            ->update(collect(range(1, 11))
                ->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => in_array($id, $yes, true)])
                ->all());

        return $record->fresh('assessmentAnswers');
    };

    $this->actingAs($officer);
    $column = Livewire::test(ListLawEnforcementAssessments::class)
        ->instance()->getTable()->getColumn('risk_count');
    $color = fn ($record) => $column->record($record)->getColor(null);

    expect($color($withYes([2], 'Q2Only')))->toBe('danger')
        ->and($color($withYes([4, 5, 6, 7], 'FourYes')))->toBe('danger')
        ->and($color($withYes([4, 5, 6], 'ThreeYes')))->toBe('warning')
        ->and($color($withYes([], 'NoYes')))->toBe('gray');
});
