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

test('the buttons sit on the right, with Edit on My Assessments only', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD')));
    expect(recordActionsOf(ListLawEnforcementAssessments::class))->toBe(['changeLog', 'view', 'edit']);
});

test('Assessment review has no Edit button', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Columns PD')));
    expect(recordActionsOf(ListAssessmentReview::class))->toBe(['changeLog', 'view']);
});

test('relationship is its own column, not a line under the offender', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Columns PD'));
    $assessment = pestOfficerAssessment($officer, 'Rel', ['OffenderVictimRelationship' => 'Ex-partner']);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertTableColumnStateSet('OffenderVictimRelationship', 'Ex-partner', $assessment)
        ->assertTableColumnDoesNotHaveDescription('OffenderLastName', 'Ex-partner', $assessment);
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
