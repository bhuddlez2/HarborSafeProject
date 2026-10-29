<?php

use App\Enums\FollowUpKind;
use App\Enums\LogAction;
use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Models\AssessmentAccessLog;
use App\Models\AssessmentEdit;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\User;
use App\Services\AssessmentEditor;
use App\Services\AssessmentPdf;
use App\Services\FollowUps;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
Finishing Touches item 28: every way staff can open, download or edit an
officer or civilian assessment is recorded, and the row appears on Logs.

Each path below is driven the way staff use it - the pop-up mounted, the page
or PDF route requested, the Edit form saved - and must leave exactly one new
row by the person who did it. That row must then be on Logs for an admin,
with its action and kind, and an officer assessment's row for that agency's
police admin too.

The individual hooks, their refusals and their fail-closed behaviour are
pinned elsewhere (LawEnforcementViewLogTest, AssessmentAccessLogTest,
FollowUpPagesTest); this file is the end-to-end check that none of them is
missing from the page.
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

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// Agency A's officer, police admin and assessment, an admin, a civilian
// assessment, and a follow-up on each assessment.
function coverageWorld(): object
{
    $w = pestTwoAgencies();
    $w->admin = staffUser(UserRole::Admin);
    $w->civilian = pestCivilianAssessment();

    $followUps = app(FollowUps::class);
    $w->officerFollowUp = $followUps->open(FollowUpKind::LawEnforcementAssessment, $w->assessmentA->DocumentID);
    $w->civilianFollowUp = $followUps->open(FollowUpKind::CivilianAssessment, $w->civilian->DocumentID);

    return $w;
}

// Every access and edit row $user has made, keyed by its Logs EntryID.
function coverageRowsBy(User $user): Collection
{
    return AssessmentAccessLog::where('AccessedBy', $user->getKey())->get()->keyBy('AccessID')
        ->union(AssessmentEdit::where('ChangedBy', $user->getKey())->get()->keyBy('EditID'));
}

function coverageEdit(LawEnforcementAssessment $record, User $by): AssessmentEdit
{
    return AssessmentEditor::apply($record, ['OffenderVictimRelationship' => 'spouse'], [], 'Pest coverage', $by);
}

function coverageFollowUpPage(FollowUp $followUp, User $by): void
{
    test()->actingAs($by)->get(FollowUpResource::getUrl('view', ['record' => $followUp]))->assertOk();
}

// The Follow-ups queue, narrowed to today's submissions so the test's forms
// are on page one ahead of the local database's real ones.
function coverageQueue(): \Livewire\Features\SupportTesting\Testable
{
    $today = now(App\Support\Timezones::DISPLAY)->toDateString();

    return Livewire::test(ListFollowUps::class)
        ->filterTable('date_submitted', ['from' => $today, 'until' => $today]);
}

/*
Each path: who does it, then what they do. Set-up (an earlier edit, so a
Change log or Logs row exists to open) happens before the "before" snapshot,
so only the path's own row counts.
*/
dataset('assessment paths', [
    // Officer assessments: the pop-ups.
    'My Assessments: View' => ['officerA', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))],
    'My Assessments: Edit pop-up' => ['officerA', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($w->assessmentA))],
    'My Assessments: Change log' => ['officerA', LogAction::View, 'officer', fn (object $w) => coverageEdit($w->assessmentA, $w->officerA), fn (object $w) => Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))],
    'Assessment review: View (admin)' => ['admin', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))],
    'Assessment review: View (police admin)' => ['policeAdminA', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))],
    'Assessment review: Edit pop-up (police admin)' => ['policeAdminA', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make(EditAction::class)->table($w->assessmentA))],
    'Assessment review: Log' => ['admin', LogAction::View, 'officer', null, fn (object $w) => Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))],
    'Logs: an edit row\'s View' => ['admin', LogAction::View, 'officer', fn (object $w) => $w->edit = coverageEdit($w->assessmentA, $w->officerA), fn (object $w) => Livewire::test(ListLogs::class)
        ->mountAction(TestAction::make('view')->table($w->edit))],
    'Follow-up page (officer assessment)' => ['admin', LogAction::View, 'officer', null, fn (object $w) => coverageFollowUpPage($w->officerFollowUp, $w->admin)],
    'Follow-ups queue: Log (officer assessment)' => ['admin', LogAction::View, 'officer', null, fn (object $w) => coverageQueue()
        ->mountAction(TestAction::make('log')->table($w->officerFollowUp))
        ->assertActionMounted(TestAction::make('log')->table($w->officerFollowUp))],

    // Officer assessments: saved edits.
    'My Assessments: Edit saved' => ['officerA', LogAction::Edit, 'officer', null, fn (object $w) => Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($w->assessmentA), ['OffenderVictimRelationship' => 'spouse', 'reason' => 'Pest coverage'])
        ->assertHasNoFormErrors()],
    'Assessment review: Edit saved (police admin)' => ['policeAdminA', LogAction::Edit, 'officer', null, fn (object $w) => Livewire::test(ListAssessmentReview::class)
        ->callAction(TestAction::make(EditAction::class)->table($w->assessmentA), ['OffenderVictimRelationship' => 'spouse', 'reason' => 'Pest coverage'])
        ->assertHasNoFormErrors()],

    // Officer assessments: the PDF, the one route every Download PDF button
    // uses (see the next test).
    'Officer PDF (officer)' => ['officerA', LogAction::Download, 'officer', null, fn (object $w) => test()->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))->assertOk()],
    'Officer PDF (police admin)' => ['policeAdminA', LogAction::Download, 'officer', null, fn (object $w) => test()->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))->assertOk()],
    'Officer PDF (admin)' => ['admin', LogAction::Download, 'officer', null, fn (object $w) => test()->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))->assertOk()],

    // Civilian assessments.
    'Civilian assessments: View' => ['admin', LogAction::View, 'civilian', null, fn (object $w) => Livewire::test(ListCivilianAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->civilian))],
    'Follow-up page (civilian assessment)' => ['admin', LogAction::View, 'civilian', null, fn (object $w) => coverageFollowUpPage($w->civilianFollowUp, $w->admin)],
    'Follow-ups queue: Log (civilian assessment)' => ['admin', LogAction::View, 'civilian', null, fn (object $w) => coverageQueue()
        ->mountAction(TestAction::make('log')->table($w->civilianFollowUp))
        ->assertActionMounted(TestAction::make('log')->table($w->civilianFollowUp))],
    'Civilian PDF' => ['admin', LogAction::Download, 'civilian', null, fn (object $w) => test()->get(route('staff.civilian-assessments.pdf', $w->civilian->DocumentID))->assertOk()],

    // The follow-up page's "Download PDF with log" (Burchfield B43): its own
    // kind of row, which a police admin sees as a plain download.
    'Follow-up PDF with log (officer assessment)' => ['admin', LogAction::DownloadWithLog, 'officer', null, fn (object $w) => test()->get(route('staff.follow-ups.pdf-with-log', $w->officerFollowUp->getKey()))->assertOk()],
    'Follow-up PDF with log (civilian assessment)' => ['admin', LogAction::DownloadWithLog, 'civilian', null, fn (object $w) => test()->get(route('staff.follow-ups.pdf-with-log', $w->civilianFollowUp->getKey()))->assertOk()],
]);

test('every view, PDF download and edit of an assessment is recorded once and listed on Logs', function (string $who, LogAction $action, string $kind, ?Closure $setUp, Closure $act) {
    $w = coverageWorld();
    $actor = $w->{$who};

    // A unique name, so the search box narrows the shared local database to
    // this person's rows.
    $actor->forceFill(['last_name' => 'Covered'.Str::random(6)])->save();
    $actor->refresh();

    $this->actingAs($actor);

    if ($setUp !== null) {
        $setUp($w);
    }

    $before = coverageRowsBy($actor)->keys();

    $act($w);

    $new = coverageRowsBy($actor)->except($before->all());

    // A saved edit is two rows: opening the Edit pop-up is a view, then the
    // save is the edit. Everything else is one.
    $edits = $new->filter(fn ($row): bool => $row instanceof AssessmentEdit);

    if ($action === LogAction::Edit) {
        expect($new)->toHaveCount(2)
            ->and($edits)->toHaveCount(1)
            ->and($new->reject(fn ($row): bool => $row instanceof AssessmentEdit)->first()->Action->value)->toBe(LogAction::View->value);

        $row = $edits->first();
    } else {
        expect($new)->toHaveCount(1);

        $row = $new->first();
    }
    $label = $kind === 'officer' ? 'Officer Assessment' : 'Civilian Assessment';

    if ($row instanceof AssessmentAccessLog) {
        expect($row->Action->value)->toBe($action->value)
            ->and($kind === 'officer' ? $row->LawEnforcementDocumentID : $row->PrivateDocumentID)
            ->toBe($kind === 'officer' ? $w->assessmentA->DocumentID : $w->civilian->DocumentID);
    } else {
        expect($action)->toBe(LogAction::Edit)
            ->and($row->DocumentID)->toBe($w->assessmentA->DocumentID);
    }

    // Listed for an admin, and an officer assessment's row for its agency's
    // police admin too.
    $viewers = $kind === 'officer' ? [$w->admin, $w->policeAdminA] : [$w->admin];

    foreach ($viewers as $viewer) {
        $this->actingAs($viewer);

        // What this viewer is shown: a police admin sees a download with the
        // follow-up log as a plain download, and finds it under that choice.
        $shown = $action->shownTo($viewer);

        Livewire::test(ListLogs::class)
            ->set('show', $shown->value)
            ->searchTable($actor->last_name)
            ->assertCanSeeTableRecords([$row])
            ->assertTableColumnFormattedStateSet('Action', $shown->label(), $row)
            ->assertTableColumnStateSet('record', $label, $row);
    }
})->with('assessment paths');

// Each screen's Download PDF goes through one of the two logged routes, so
// the download rows above cover every screen.
test('every Download PDF button on an assessment uses the logged PDF route', function () {
    $w = coverageWorld();
    $officerPdf = route('staff.assessments.pdf', $w->assessmentA->DocumentID);
    $civilianPdf = route('staff.civilian-assessments.pdf', $w->civilian->DocumentID);

    $this->actingAs($w->officerA);
    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionHasUrl(TestAction::make('download_pdf')->table($w->assessmentA), $officerPdf);

    $this->actingAs($w->policeAdminA);
    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionHasUrl(TestAction::make('download_pdf')->table($w->assessmentA), $officerPdf);

    $this->actingAs($w->admin);
    Livewire::test(ListCivilianAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->civilian))
        ->assertActionHasUrl(TestAction::make('download_pdf')->table($w->civilian), $civilianPdf);

    $this->get(FollowUpResource::getUrl('view', ['record' => $w->officerFollowUp]))->assertOk()->assertSee($officerPdf, false);
    $this->get(FollowUpResource::getUrl('view', ['record' => $w->civilianFollowUp]))->assertOk()->assertSee($civilianPdf, false);
});

// The queue's Log pop-up shows the notes, which can hold names, so opening it
// is a view wherever it opens (AS, 2026-10-27). A resource request's opening
// is recorded like its follow-up page, but never listed on Logs; and if the
// view can't be written, the pop-up doesn't open.
test('opening a resource request\'s Log on the queue is recorded, not listed, and fails closed', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $rows = fn () => AssessmentAccessLog::where('ResourceRequestFormID', $followUp->ResourceRequestFormID)->get();
    $this->actingAs($admin);

    coverageQueue()->mountAction(TestAction::make('log')->table($followUp));

    expect($rows())->toHaveCount(1)
        ->and($rows()[0]->Action->value)->toBe(LogAction::View->value)
        ->and($rows()[0]->AccessedBy)->toBe($admin->getKey())
        ->and(App\Filament\Resources\Logs\LogResource::getEloquentQuery()->whereKey($rows()[0]->AccessID)->exists())->toBeFalse();

    $failing = true;
    AssessmentAccessLog::creating(function () use (&$failing) {
        if ($failing) {
            throw new RuntimeException('access log unavailable');
        }
    });

    $queue = coverageQueue();

    try {
        expect(fn () => $queue->mountAction(TestAction::make('log')->table($followUp)))
            ->toThrow(RuntimeException::class, 'access log unavailable');
    } finally {
        $failing = false;
    }

    expect($rows())->toHaveCount(1);
});
