<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\Police;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Models\AssessmentAccessLog;
use App\Models\AssessmentEdit;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
Team plan task 7. Police admins (with an agency) submit their OWN assessments
through the officers' wizard, unchanged, and edit any assessment in their
agency - their officers' and their own - from Assessment review, through the
same shared Edit as My Assessments (App\Filament\Assessments\AssessmentEditForm).

Section 5 rule 1, widened on purpose for police admins of the same agency
only: the submitting officer or an active police admin of the submitter's
agency may edit; admin is view-only; nobody else edits.
*/

afterEach(fn () => cleanupStaffData());

// pestOfficerAssessment() stores no relationship, which the edit form
// requires, so an edit of one of its records must pick one.
const PA7_VALID_EDIT = ['OffenderVictimRelationship' => 'spouse'];

// A completed wizard, ready to submit.
function pa7WizardData(array $overrides = []): array
{
    return [
        'VictimFirstName' => 'Task',
        'VictimLastName' => 'Seven',
        'VictimDOB' => '1990-01-01',
        'VictimSex' => 'F',
        'VictimSafePhoneNumber' => '423-555-0150',
        'OffenderFirstName' => 'Task',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
        'SafePhoneConfirmed' => true,
        ...collect(range(1, 11))->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => $id === 1])->all(),
        ...$overrides,
    ];
}

// Every law_enforcement_agents row of these users, to prove nothing wrote one.
function pa7AgentRows(User ...$users): array
{
    return DB::connection('Portal')->table('law_enforcement_agents')
        ->whereIn('user_id', collect($users)->map->getKey())
        ->orderBy('user_id')
        ->get()
        ->map(fn ($row): array => (array) $row)
        ->all();
}

// The sidebar's item labels for the signed-in user.
function pa7SidebarItems(): array
{
    Filament::setCurrentPanel('staff');

    return collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->values()
        ->all();
}

// Forces the Edit action through Livewire directly, as a tampered browser
// would - Filament's test helpers refuse to call a hidden action. A record
// outside the user's scope can't even be resolved, which is a refusal too.
function pa7ForceEdit(Testable $list, LawEnforcementAssessment $record): void
{
    try {
        $list->call('mountAction', 'edit', [], ['table' => true, 'recordKey' => $record->getKey()])
            ->set('mountedActions.0.data.VictimLastName', 'Forced')
            ->set('mountedActions.0.data.reason', 'Forced')
            ->call('callMountedAction');
    } catch (ActionNotResolvableException) {
        // Refused: the scoped table query has no such record for this user.
    }
}

function pa7ViewsBy(User $user, LawEnforcementAssessment $record): int
{
    return AssessmentAccessLog::where('LawEnforcementDocumentID', $record->DocumentID)
        ->where('Action', AssessmentAccess::View->value)
        ->where('AccessedBy', $user->getKey())
        ->count();
}

// --- Submitting --------------------------------------------------------------------

test('a police admin with an agency submits their own assessment', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    Livewire::test(NewAssessment::class)
        ->fillForm(pa7WizardData())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    $own = LawEnforcementAssessment::where('submitted_by', $w->policeAdminA->getKey())->sole();

    // Followed up like every other assessment.
    expect(FollowUp::where('LawEnforcementDocumentID', $own->DocumentID)->exists())->toBeTrue();

    // In their own Assessment review...
    Livewire::test(ListAssessmentReview::class)->assertCanSeeTableRecords([$own]);

    // ...and in no officer's My Assessments.
    foreach ([$w->officerA, $w->officerB] as $officer) {
        $this->actingAs($officer);
        Livewire::test(ListLawEnforcementAssessments::class)->assertCanNotSeeTableRecords([$own]);
    }
});

test('a tampered submitted_by is ignored', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    Livewire::test(NewAssessment::class)
        ->fillForm(pa7WizardData(['submitted_by' => $w->officerA->getKey()]))
        ->call('create')
        ->assertSet('submitted', true);

    $submittedBy = fn (User $user): Closure => fn ($query) => $query->where('submitted_by', $user->getKey());

    expect(encryptedRows(LawEnforcementAssessment::class, 'VictimLastName', 'Seven', scope: $submittedBy($w->officerA)))->toBeEmpty()
        ->and(encryptedRows(LawEnforcementAssessment::class, 'VictimLastName', 'Seven', scope: $submittedBy($w->policeAdminA)))->toHaveCount(1);
});

test('a police admin with no agency can\'t open New assessment, and it isn\'t offered', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, null));

    expect(NewAssessment::canAccess())->toBeFalse()
        ->and(pa7SidebarItems())->not->toContain('New Assessment');

    $this->get(NewAssessment::getUrl())->assertForbidden();

    $this->get(Police::getUrl())
        ->assertOk()
        ->assertDontSee('Start New Lethality Assessment');
});

test('the Submitting officer step shows "Police admin" for a police admin\'s badge, and the officer\'s real one', function () {
    $w = pestTwoAgencies();
    $before = pa7AgentRows($w->policeAdminA, $w->officerA);
    $storedBadge = $w->policeAdminA->lawEnforcementAgent->badge_number;
    $agency = $w->policeAdminA->officerIdentity()['agency'];

    $this->actingAs($w->policeAdminA);
    Livewire::test(NewAssessment::class)
        ->assertSeeInOrder([$w->policeAdminA->name, User::POLICE_ADMIN_BADGE, $agency])
        ->assertDontSee($storedBadge)
        ->fillForm(pa7WizardData())
        ->call('create')
        ->assertSet('submitted', true);

    $this->actingAs($w->officerA);
    $officerBadge = $w->officerA->lawEnforcementAgent->badge_number;
    Livewire::test(NewAssessment::class)
        ->assertSee($officerBadge)
        ->assertDontSee(User::POLICE_ADMIN_BADGE);

    // Display only: nothing wrote a badge.
    expect(pa7AgentRows($w->policeAdminA, $w->officerA))->toBe($before)
        ->and($w->officerA->displayBadge())->toBe($officerBadge)
        ->and($w->policeAdminA->displayBadge())->toBe(User::POLICE_ADMIN_BADGE);
});

test('the wizard is the same for officers and police admins: same steps, same step positions', function () {
    $w = pestTwoAgencies();
    $steps = function (): array {
        $wizard = Livewire::test(NewAssessment::class)->instance()->form->getComponents()[0];

        return collect($wizard->getChildSchema()->getComponents())
            ->map(fn ($step): string => (string) $step->getLabel())
            ->all();
    };

    $this->actingAs($w->officerA);
    $officerSteps = $steps();

    expect($officerSteps)->toBe([
        'Submitting officer', 'About the victim', 'About the offender',
        'Lethality Assessment', 'Questions', 'Review & Submit',
    ]);

    foreach ([$w->officerA, $w->policeAdminA] as $user) {
        $this->actingAs($user);

        expect($steps())->toBe($officerSteps);

        // The questions step is still index 4, "Begin assessment" index 3.
        Livewire::test(NewAssessment::class)
            ->assertSeeHtml('getStepIndex(step) : 0) === 4')
            ->assertSeeHtml('getStepIndex(step) : 0) === 3 ?');
    }
});

// --- Editing -----------------------------------------------------------------------

test('a police admin edits their officer\'s assessment and their own, recorded as theirs', function (string $whose) {
    $w = pestTwoAgencies();
    $record = $whose === 'officer\'s'
        ? $w->assessmentA
        : pestOfficerAssessment($w->policeAdminA, 'PoliceAdminOwn');
    $this->actingAs($w->policeAdminA);

    // Opening the pop-up logs exactly one view.
    Livewire::test(ListAssessmentReview::class)
        ->assertActionVisible(TestAction::make(EditAction::class)->table($record))
        ->mountAction(TestAction::make(EditAction::class)->table($record));

    expect(pa7ViewsBy($w->policeAdminA, $record))->toBe(1);

    // A reason is required.
    Livewire::test(ListAssessmentReview::class)
        ->callAction(TestAction::make(EditAction::class)->table($record), [...PA7_VALID_EDIT, 'VictimLastName' => 'Amended', 'reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect(AssessmentEdit::where('DocumentID', $record->DocumentID)->exists())->toBeFalse();

    Livewire::test(ListAssessmentReview::class)
        ->callAction(TestAction::make(EditAction::class)->table($record), [...PA7_VALID_EDIT, 'VictimLastName' => 'Amended', 'reason' => 'Spelling'])
        ->assertHasNoFormErrors();

    $edit = AssessmentEdit::where('DocumentID', $record->DocumentID)->sole();

    expect($edit->ChangedBy)->toBe($w->policeAdminA->getKey())
        ->and($edit->Reason)->toBe('Spelling')
        ->and($edit->detailChanges()->pluck('ChangedBy')->unique()->values()->all())->toBe([$w->policeAdminA->getKey()])
        ->and($record->refresh()->VictimLastName)->toBe('Amended');

    // Listed under their name on Logs, as Edited.
    Livewire::test(ListLogs::class)
        ->assertCanSeeTableRecords([$edit])
        ->assertTableColumnStateSet('user.name', $w->policeAdminA->name, $edit)
        ->assertTableColumnFormattedStateSet('Action', 'Edited', $edit);
})->with(['officer\'s', 'own']);

test('if the view can\'t be logged, a police admin\'s Edit pop-up doesn\'t open', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);
    $list = Livewire::test(ListAssessmentReview::class);

    $failing = true;
    AssessmentAccessLog::creating(function () use (&$failing) {
        if ($failing) {
            throw new RuntimeException('access log unavailable');
        }
    });

    try {
        expect(fn () => $list->mountAction(TestAction::make(EditAction::class)->table($w->assessmentA)))
            ->toThrow(RuntimeException::class, 'access log unavailable');
    } finally {
        $failing = false;
    }

    expect(AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->DocumentID)->exists())->toBeFalse();
});

test('another agency\'s police admin has no Edit, and a forced call changes nothing', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminB);

    $list = Livewire::test(ListAssessmentReview::class)
        ->assertCanNotSeeTableRecords([$w->assessmentA]);

    pa7ForceEdit($list, $w->assessmentA);

    expect($w->policeAdminB->can('update', $w->assessmentA))->toBeFalse()
        ->and(AssessmentEdit::where('DocumentID', $w->assessmentA->DocumentID)->exists())->toBeFalse()
        ->and($w->assessmentA->refresh()->VictimLastName)->not->toBe('Forced');
});

test('an admin has no Edit anywhere, and a forced call changes nothing', function () {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $this->actingAs($admin);

    $list = Livewire::test(ListAssessmentReview::class)
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertActionHidden(TestAction::make(EditAction::class)->table($w->assessmentA));

    pa7ForceEdit($list, $w->assessmentA);

    expect($admin->can('update', $w->assessmentA))->toBeFalse()
        ->and(AssessmentEdit::where('DocumentID', $w->assessmentA->DocumentID)->exists())->toBeFalse()
        ->and($w->assessmentA->refresh()->VictimLastName)->not->toBe('Forced');

    // And My Assessments, the other screen with Edit, is closed to admins.
    $this->get(App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource::getUrl('index'))
        ->assertForbidden();
});

test('officers still edit only their own', function () {
    $w = pestTwoAgencies();
    $policeAdminOwn = pestOfficerAssessment($w->policeAdminA, 'PoliceAdminOwn');

    expect($w->officerA->can('update', $w->assessmentA))->toBeTrue()
        ->and($w->officerA->can('update', $policeAdminOwn))->toBeFalse()
        ->and($w->officerA->can('update', $w->assessmentB))->toBeFalse();

    // A police admin's submission is never in an officer's list to edit.
    $this->actingAs($w->officerA);
    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertCanNotSeeTableRecords([$policeAdminOwn])
        ->callAction(TestAction::make(EditAction::class)->table($w->assessmentA), [...PA7_VALID_EDIT, 'VictimLastName' => 'Officer', 'reason' => 'My correction'])
        ->assertHasNoFormErrors();

    expect(AssessmentEdit::where('DocumentID', $w->assessmentA->DocumentID)->sole()->ChangedBy)->toBe($w->officerA->getKey());
});
