<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Services\AssessmentEditor;
use App\Services\AssessmentPdf;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
View logging for law-enforcement assessments (team plan task 9): every pop-up
that shows a record's contents writes one 'view' row in
assessment_access_log - View (My Assessments, Assessment review), Edit (My
Assessments), the per-row Change log, and the Change log screen's View. Once
per open, only after the open is allowed, and a failed write stops the
pop-up opening. The hooks live in App\Filament\Assessments\AssessmentViews.

The civilian View and every PDF download are covered in
AssessmentAccessLogTest.
*/

beforeEach(function () {
    $this->instance(AssessmentPdf::class, new class extends AssessmentPdf
    {
        public function render(LawEnforcementAssessment | PrivateAssessment | App\Models\ResourceRequestForm | App\Models\ServiceFeedback $record): string
        {
            return '%PDF-fake';
        }
    });
});

afterEach(fn () => cleanupStaffData());

function leAccessRows(LawEnforcementAssessment $record, ?AssessmentAccess $action = null): Illuminate\Support\Collection
{
    return AssessmentAccessLog::where('LawEnforcementDocumentID', $record->getKey())
        ->when($action, fn ($query) => $query->where('Action', $action))
        ->get();
}

// Exactly one view row, by $user, pointing at the assessment and nothing else.
function expectOneView(LawEnforcementAssessment $record, App\Models\User $user): void
{
    $rows = leAccessRows($record);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::View)
        ->and($rows[0]->AccessedBy)->toBe($user->getKey())
        ->and($rows[0]->PrivateDocumentID)->toBeNull()
        ->and($rows[0]->ResourceRequestFormID)->toBeNull()
        ->and($rows[0]->AccessedAt)->not->toBeNull();
}

function leEdit(LawEnforcementAssessment $record, App\Models\User $officer): AssessmentEdit
{
    return AssessmentEditor::apply($record, ['VictimLastName' => 'Amended'], [], 'Spelling', $officer);
}

// Makes the next log write fail, the way a lost database connection would,
// for the duration of $open. The listener stays registered but goes inert.
function whileLogWritesFail(Closure $open): void
{
    $failing = true;

    AssessmentAccessLog::creating(function () use (&$failing) {
        if ($failing) {
            throw new RuntimeException('access log unavailable');
        }
    });

    try {
        expect($open)->toThrow(RuntimeException::class, 'access log unavailable');
    } finally {
        $failing = false;
    }
}

// --- Each pop-up logs one view -------------------------------------------

test('an officer opening View on their own assessment is logged once', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionMounted(TestAction::make('view')->table($w->assessmentA))
        ->assertMountedActionModalSee('(423)555-0100');

    expectOneView($w->assessmentA, $w->officerA);
});

test('an officer opening Edit on their own assessment is logged once', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($w->assessmentA))
        ->assertActionMounted(TestAction::make(EditAction::class)->table($w->assessmentA));

    expectOneView($w->assessmentA, $w->officerA);
});

test('an officer opening the Change log pop-up on their own assessment is logged once', function () {
    $w = pestTwoAgencies();
    leEdit($w->assessmentA, $w->officerA);
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))
        ->assertActionMounted(TestAction::make('changeLog')->table($w->assessmentA))
        ->assertMountedActionModalSee('Spelling');

    expectOneView($w->assessmentA, $w->officerA);
});

// Officers can't reach the Change log screen (admins and police admins only).
test('opening View on the Change log screen is logged once against the assessment', function (string $who) {
    $w = pestTwoAgencies();
    $edit = leEdit($w->assessmentA, $w->officerA);
    $user = $who === 'admin' ? staffUser(UserRole::Admin) : $w->policeAdminA;
    $this->actingAs($user);

    Livewire::test(ListAssessmentEdits::class)
        ->mountAction(TestAction::make('view')->table($edit))
        ->assertActionMounted(TestAction::make('view')->table($edit))
        ->assertMountedActionModalSee('Spelling');

    expectOneView($w->assessmentA, $user);
})->with(['police admin', 'admin']);

test('a police admin opening View in Assessment review on their agency\'s record is logged', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionMounted(TestAction::make('view')->table($w->assessmentA));

    expectOneView($w->assessmentA, $w->policeAdminA);
});

test('an admin opening View in Assessment review is logged', function () {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $this->actingAs($admin);

    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentB))
        ->assertActionMounted(TestAction::make('view')->table($w->assessmentB));

    expectOneView($w->assessmentB, $admin);
});

test('an admin opening the Change log pop-up in Assessment review is logged', function () {
    $w = pestTwoAgencies();
    leEdit($w->assessmentB, $w->officerB);
    $admin = staffUser(UserRole::Admin);
    $this->actingAs($admin);

    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentB))
        ->assertActionMounted(TestAction::make('changeLog')->table($w->assessmentB));

    expectOneView($w->assessmentB, $admin);
});

// --- Refused opens log nothing ---------------------------------------------

test('opening a record outside the user\'s scope writes nothing', function () {
    $w = pestTwoAgencies();
    $editB = leEdit($w->assessmentB, $w->officerB);

    // An officer, on another agency's officer's record.
    $this->actingAs($w->officerA);
    $mine = Livewire::test(ListLawEnforcementAssessments::class);

    foreach (['view', EditAction::class, 'changeLog'] as $action) {
        $mine->mountAction(TestAction::make($action)->table($w->assessmentB))
            ->assertActionNotMounted(TestAction::make($action)->table($w->assessmentB));
    }

    // A police admin, on another agency's record and its edit.
    $this->actingAs($w->policeAdminA);
    $review = Livewire::test(ListAssessmentReview::class);

    foreach (['view', 'changeLog'] as $action) {
        $review->mountAction(TestAction::make($action)->table($w->assessmentB))
            ->assertActionNotMounted(TestAction::make($action)->table($w->assessmentB));
    }

    Livewire::test(ListAssessmentEdits::class)
        ->mountAction(TestAction::make('view')->table($editB))
        ->assertActionNotMounted(TestAction::make('view')->table($editB));

    expect(leAccessRows($w->assessmentB))->toBeEmpty();
});

test('the Change log button on a never-edited record opens nothing and logs nothing', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))
        ->assertActionNotMounted(TestAction::make('changeLog')->table($w->assessmentA));

    expect(leAccessRows($w->assessmentA))->toBeEmpty();
});

// --- Once per open ----------------------------------------------------------

test('re-rendering inside an open pop-up adds no rows', function () {
    $w = pestTwoAgencies();
    $edit = leEdit($w->assessmentA, $w->officerA);

    $this->actingAs($w->officerA);

    foreach (['view', 'changeLog'] as $action) {
        Livewire::test(ListLawEnforcementAssessments::class)
            ->mountAction(TestAction::make($action)->table($w->assessmentA))
            ->call('$refresh')
            ->call('$refresh')
            ->assertActionMounted(TestAction::make($action)->table($w->assessmentA));
    }

    // Typing into the Edit form is a round trip per change.
    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($w->assessmentA))
        ->fillForm(['VictimFirstName' => 'Typed'])
        ->fillForm(['reason' => 'Still typing'])
        ->call('$refresh')
        ->assertActionMounted(TestAction::make(EditAction::class)->table($w->assessmentA));

    expect(leAccessRows($w->assessmentA))->toHaveCount(3);

    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->mountAction(TestAction::make('view')->table($edit))
        ->call('$refresh')
        ->call('$refresh')
        ->assertActionMounted(TestAction::make('view')->table($edit));

    expect(leAccessRows($w->assessmentA)->where('AccessedBy', $w->policeAdminA->getKey()))->toHaveCount(1);
});

// --- Fail closed --------------------------------------------------------------

/*
The hooks run inside Filament's mountAction(), before it sends the modal to
the browser (syncActionModals()). A throw there fails the whole Livewire
request, so the browser never receives the open pop-up.
*/
test('if the view can\'t be logged, the pop-up doesn\'t open', function (string $page, string $action, string $who) {
    $w = pestTwoAgencies();
    $edit = leEdit($w->assessmentA, $w->officerA);

    $this->actingAs($who === 'officer' ? $w->officerA : $w->policeAdminA);
    $record = $page === ListAssessmentEdits::class ? $edit : $w->assessmentA;
    $list = Livewire::test($page);

    whileLogWritesFail(fn () => $list->mountAction(TestAction::make($action)->table($record)));

    expect(leAccessRows($w->assessmentA))->toBeEmpty();
})->with([
    'View' => [ListLawEnforcementAssessments::class, 'view', 'officer'],
    'Edit' => [ListLawEnforcementAssessments::class, EditAction::class, 'officer'],
    'Change log pop-up' => [ListLawEnforcementAssessments::class, 'changeLog', 'officer'],
    'Change log screen View' => [ListAssessmentEdits::class, 'view', 'police admin'],
]);

// --- PDF downloads ---------------------------------------------------------------

test('a PDF download writes one download row and no view row', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    $this->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))->assertOk();

    expect(leAccessRows($w->assessmentA, AssessmentAccess::Download))->toHaveCount(1)
        ->and(leAccessRows($w->assessmentA, AssessmentAccess::View))->toBeEmpty();

    // Opening View and then downloading: one of each, not two downloads.
    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionVisible(TestAction::make('download_pdf')->table($w->assessmentA));

    $this->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))->assertOk();

    expect(leAccessRows($w->assessmentA, AssessmentAccess::Download))->toHaveCount(2)
        ->and(leAccessRows($w->assessmentA, AssessmentAccess::View))->toHaveCount(1);
});
