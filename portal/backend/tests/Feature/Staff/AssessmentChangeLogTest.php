<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentEdits\AssessmentEditResource;
use App\Filament\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\Agency;
use App\Models\AssessmentAnswerChangeLog;
use App\Models\AssessmentAnswers;
use App\Models\AssessmentChangeLog;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use App\Services\AssessmentEditor;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
The change log behind officer edits (Filament_CMS_Design.md section 8): every
save of a law-enforcement assessment is one assessment_edits row - who, when,
why - with one field-level row per changed column, and nothing can change an
assessment without going through App\Services\AssessmentEditor.
*/

afterEach(fn () => cleanupStaffData());

function changeLogAgency(string $label): Agency
{
    return Agency::create(['name' => staffRunToken().' '.$label]);
}

function changeLogMember(UserRole $role, ?Agency $agency): User
{
    $user = staffUser($role);

    LawEnforcementAgent::create([
        'user_id' => $user->getKey(),
        'badge_number' => 'CL-'.$user->getKey(),
        'agency_id' => $agency?->getKey(),
    ]);

    return $user;
}

function changeLogAssessment(User $officer): LawEnforcementAssessment
{
    $answers = AssessmentAnswers::create(collect(AssessmentEditor::answerFields())
        ->mapWithKeys(fn (string $field): array => [$field => $field === 'RiskIndicator1'])
        ->all());

    return LawEnforcementAssessment::create([
        'submitted_by' => $officer->getKey(),
        'VictimFirstName' => 'Test',
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'Spouse',
        'AssessmentDocID' => $answers->getKey(),
    ]);
}

test('one save is one edit with a row per changed field, raw values, reason and editor', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    $edit = AssessmentEditor::apply(
        $assessment,
        ['VictimLastName' => 'Smith', 'OffenderDOB' => '1988-06-30', 'VictimFirstName' => 'Test'],
        ['RiskIndicator3' => true, 'RiskIndicator1' => true],
        'Corrected surname; added DOB; Q3 answered yes on review',
        $officer,
    );

    expect($edit)->toBeInstanceOf(AssessmentEdit::class)
        ->and($edit->Reason)->toBe('Corrected surname; added DOB; Q3 answered yes on review')
        ->and($edit->ChangedBy)->toBe($officer->getKey())
        ->and(AssessmentEdit::where('DocumentID', $assessment->getKey())->count())->toBe(1);

    // Unchanged fields (VictimFirstName, RiskIndicator1) are not logged.
    $details = AssessmentChangeLog::where('EditID', $edit->getKey())->get()->keyBy('ChangeField');
    $answers = AssessmentAnswerChangeLog::where('EditID', $edit->getKey())->get()->keyBy('ChangeField');

    expect($details->keys()->sort()->values()->all())->toBe(['OffenderDOB', 'VictimLastName'])
        ->and($details['VictimLastName']->PreviousValue)->toBe('Doe')
        ->and($details['VictimLastName']->NewValue)->toBe('Smith')
        ->and($details['OffenderDOB']->PreviousValue)->toBeNull()
        ->and($answers->keys()->all())->toBe(['RiskIndicator3'])
        ->and((bool) $answers['RiskIndicator3']->PreviousValue)->toBeFalse()
        ->and((bool) $answers['RiskIndicator3']->NewValue)->toBeTrue();

    // And the record itself changed.
    expect($assessment->refresh()->VictimLastName)->toBe('Smith')
        ->and((bool) $assessment->assessmentAnswers->RiskIndicator3)->toBeTrue();

    // Readable, for the history.
    expect($edit->changes())->toContain(
        ['label' => 'Victim last name', 'from' => 'Doe', 'to' => 'Smith'],
        ['label' => 'Offender date of birth', 'from' => '—', 'to' => 'Jun 30, 1988'],
    );
});

test('a save that changes nothing records nothing', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    // Same values, an empty string for a null, and true for a stored 1.
    $edit = AssessmentEditor::apply(
        $assessment,
        ['VictimLastName' => 'Doe', 'VictimDOB' => ''],
        ['RiskIndicator1' => true, 'RiskIndicator2' => false],
        'No real change',
        $officer,
    );

    expect($edit)->toBeNull()
        ->and(AssessmentEdit::where('DocumentID', $assessment->getKey())->count())->toBe(0);
});

test('an assessment cannot be changed outside the editor, and the log cannot be rewritten', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    expect(fn () => $assessment->update(['VictimLastName' => 'Sneaky']))->toThrow(LogicException::class)
        ->and(fn () => $assessment->assessmentAnswers->update(['RiskIndicator2' => true]))->toThrow(LogicException::class)
        ->and($assessment->refresh()->VictimLastName)->toBe('Doe');

    $edit = AssessmentEditor::apply($assessment, ['VictimLastName' => 'Smith'], [], 'Spelling', $officer);
    $row = AssessmentChangeLog::where('EditID', $edit->getKey())->firstOrFail();

    expect(fn () => $edit->update(['Reason' => 'Rewritten']))->toThrow(LogicException::class)
        ->and(fn () => $edit->delete())->toThrow(LogicException::class)
        ->and(fn () => $row->update(['NewValue' => 'Rewritten']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

test('the officer edits through the pop-up, and a reason is required', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimLastName' => 'Smith',
            'reason' => '',
        ])
        ->assertHasFormErrors(['reason' => 'required']);

    expect(AssessmentEdit::where('DocumentID', $assessment->getKey())->count())->toBe(0);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'VictimLastName' => 'Smith',
            'RiskIndicator5' => true,
            'reason' => 'Corrected spelling; Q5 was missed',
        ])
        ->assertHasNoFormErrors();

    $edit = AssessmentEdit::where('DocumentID', $assessment->getKey())->sole();

    expect($edit->Reason)->toBe('Corrected spelling; Q5 was missed')
        ->and($edit->detailChanges()->pluck('ChangeField')->all())->toBe(['VictimLastName'])
        ->and($edit->answerChanges()->pluck('ChangeField')->all())->toBe(['RiskIndicator5']);
});

test('nobody but the submitting officer is offered the edit', function () {
    $agency = changeLogAgency('A');
    $officer = changeLogMember(UserRole::LawEnforcement, $agency);
    $assessment = changeLogAssessment($officer);

    // Admin and police admin review it - and have no edit action there.
    foreach ([staffUser(UserRole::Admin), changeLogMember(UserRole::PoliceAdmin, $agency)] as $reviewer) {
        $this->actingAs($reviewer);

        Livewire::test(ListAssessmentReview::class)
            ->assertCanSeeTableRecords([$assessment])
            ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($assessment));
    }

    expect(changeLogMember(UserRole::LawEnforcement, $agency)->can('update', $assessment))->toBeFalse()
        ->and($officer->can('update', $assessment))->toBeTrue();
});

test('the change log page shows admins every edit and police admins their own agency\'s', function () {
    $agencyA = changeLogAgency('A');
    $agencyB = changeLogAgency('B');
    $officerA = changeLogMember(UserRole::LawEnforcement, $agencyA);
    $officerB = changeLogMember(UserRole::LawEnforcement, $agencyB);

    $editA = AssessmentEditor::apply(changeLogAssessment($officerA), ['VictimLastName' => 'Ames'], [], 'A edit', $officerA);
    $editB = AssessmentEditor::apply(changeLogAssessment($officerB), ['VictimLastName' => 'Bell'], [], 'B edit', $officerB);

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ListAssessmentEdits::class)->assertCanSeeTableRecords([$editA, $editB]);

    $policeAdminA = changeLogMember(UserRole::PoliceAdmin, $agencyA);
    $this->actingAs($policeAdminA);
    Livewire::test(ListAssessmentEdits::class)
        ->assertCanSeeTableRecords([$editA])
        ->assertCanNotSeeTableRecords([$editB]);

    expect(AssessmentEditResource::canView($editB))->toBeFalse()
        ->and(AssessmentEditResource::canView($editA))->toBeTrue();

    foreach ([UserRole::Secretary, UserRole::LawEnforcement] as $role) {
        $this->actingAs(staffUser($role));
        expect(AssessmentEditResource::canAccess())->toBeFalse("change log for {$role->value}");
    }
});

test('the change log button shows the history with readable labels', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    AssessmentEditor::apply($assessment, ['VictimSex' => 'O'], ['RiskIndicator2' => true], 'Corrected on review', $officer);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertTableColumnFormattedStateSet('edits_count', 'Amended', $assessment)
        ->mountAction(TestAction::make('changeLog')->table($assessment))
        ->assertMountedActionModalSee([
            'Corrected on review',
            'Victim sex',
            'Female',
            'Other',
            'Q2: Have they threatened to kill you or your children?',
        ]);
});

/*
View shows the record as it is now and nothing else, so that what it shows -
and anything exported from it - is the complete current document, with no
history mixed in.
*/
test('the view pop-up shows the current record only, not its history', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    AssessmentEditor::apply($assessment, ['VictimLastName' => 'Smith'], [], 'Spelling', $officer);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($assessment))
        ->assertMountedActionModalSee('Smith')
        ->assertMountedActionModalDontSee(['Spelling', 'Doe']);
});

test('the change log button is greyed out on a record never edited', function () {
    $officer = changeLogMember(UserRole::LawEnforcement, changeLogAgency('A'));
    $assessment = changeLogAssessment($officer);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertActionDisabled(TestAction::make('changeLog')->table($assessment));

    // And the same button sits beside View for admins in Assessment review.
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListAssessmentReview::class)
        ->assertActionExists(TestAction::make('changeLog')->table($assessment));
});
