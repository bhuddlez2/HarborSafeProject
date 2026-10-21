<?php

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpKind;
use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Services\AssessmentPdf;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Every assessment PDF download (AssessmentPdfController,
CivilianAssessmentPdfController) writes exactly one 'download' row in
assessment_access_log - who, which record, when - only after the file is
rendered, and only for someone the record's visibleTo() scope allows.
Fails closed both ways: a failed render writes no row, and a failed log write
sends no file.

The other half of the picture lives elsewhere: the officer's own download
(AssessmentAccessLogTest, LawEnforcementViewLogTest), the civilian refusals
(AssessmentAccessLogTest), the My Assessments and Civilian View buttons, and
the civilian follow-up page's link.

AssessmentPdf is faked: a real render launches headless Chromium.
*/

beforeEach(function () {
    $this->pdfRenderFails = false;
    $test = $this;

    $this->instance(AssessmentPdf::class, new class($test) extends AssessmentPdf
    {
        public function __construct(private object $test) {}

        public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
        {
            if ($this->test->pdfRenderFails) {
                throw new RuntimeException('render failed');
            }

            return '%PDF-fake';
        }
    });
});

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupStaffData();
});

function pdfDownloadRows(LawEnforcementAssessment | PrivateAssessment $record): Illuminate\Support\Collection
{
    $column = $record instanceof PrivateAssessment ? 'PrivateDocumentID' : 'LawEnforcementDocumentID';

    return AssessmentAccessLog::where($column, $record->getKey())->get();
}

function pdfDownloadUrl(LawEnforcementAssessment | PrivateAssessment $record): string
{
    return $record instanceof PrivateAssessment
        ? route('staff.civilian-assessments.pdf', $record->DocumentID)
        : route('staff.assessments.pdf', $record->DocumentID);
}

function pdfDownloadFilename(LawEnforcementAssessment | PrivateAssessment $record): string
{
    $prefix = $record instanceof PrivateAssessment ? 'civilian-assessment-' : 'assessment-';

    return $prefix.substr($record->DocumentID, 0, 8).'.pdf';
}

// Every later log write throws, the way a lost database connection would.
// The listener stays registered but goes inert after $run.
function whilePdfLogWritesFail(Closure $run): void
{
    $failing = true;

    AssessmentAccessLog::creating(function () use (&$failing) {
        if ($failing) {
            throw new RuntimeException('access log unavailable');
        }
    });

    try {
        $run();
    } finally {
        $failing = false;
    }
}

// --- Allowed downloads -----------------------------------------------------------

test('an allowed download sends the PDF and writes exactly one download row', function (Closure $pick) {
    $w = pestTwoAgencies();
    $w->admin = staffUser(UserRole::Admin);
    $w->civilian = pestCivilianAssessment();
    [$user, $record] = $pick($w);

    $before = Carbon::now()->subSecond();

    $this->actingAs($user)
        ->get(pdfDownloadUrl($record))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.pdfDownloadFilename($record).'"');

    $rows = pdfDownloadRows($record);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::Download)
        ->and($rows[0]->AccessedBy)->toBe($user->getKey())
        ->and(Carbon::parse($rows[0]->AccessedAt)->between($before, Carbon::now()->addSecond()))->toBeTrue();
})->with([
    'officer, own assessment' => [fn ($w) => [$w->officerA, $w->assessmentA]],
    'police admin, own agency' => [fn ($w) => [$w->policeAdminA, $w->assessmentA]],
    'admin, agency A' => [fn ($w) => [$w->admin, $w->assessmentA]],
    'admin, agency B' => [fn ($w) => [$w->admin, $w->assessmentB]],
    'admin, civilian assessment' => [fn ($w) => [$w->admin, $w->civilian]],
]);

// --- Refusals ---------------------------------------------------------------------

test('an out-of-scope download is refused and writes no row', function (Closure $pick, int $status) {
    $w = pestTwoAgencies();
    $w->civilian = pestCivilianAssessment();
    [$user, $record] = $pick($w);

    $this->actingAs($user)
        ->get(pdfDownloadUrl($record))
        ->assertStatus($status)
        ->assertHeaderMissing('Content-Disposition');

    expect(pdfDownloadRows($record))->toBeEmpty();
})->with([
    'police admin, other agency' => [fn ($w) => [$w->policeAdminA, $w->assessmentB], 404],
    'police admin with no agency' => [fn ($w) => [pestAgencyMember(UserRole::PoliceAdmin, null), $w->assessmentA], 404],
    'officer, another officer\'s' => [fn ($w) => [$w->officerA, $w->assessmentB], 404],
    'secretary, officer assessment' => [fn ($w) => [staffUser(UserRole::Secretary), $w->assessmentA], 404],
    'inactive admin, officer assessment' => [fn ($w) => [staffUser(UserRole::Admin, isActive: false), $w->assessmentA], 403],
    'police admin, civilian assessment' => [fn ($w) => [$w->policeAdminA, $w->civilian], 404],
    'officer, civilian assessment' => [fn ($w) => [$w->officerA, $w->civilian], 404],
]);

// --- Fails closed -----------------------------------------------------------------

test('a failed render sends nothing and writes no row', function (string $who) {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $record = $who === 'civilian' ? pestCivilianAssessment() : $w->assessmentA;
    $this->pdfRenderFails = true;

    $this->actingAs($admin)
        ->get(pdfDownloadUrl($record))
        ->assertServerError()
        ->assertHeaderMissing('Content-Disposition');

    expect(pdfDownloadRows($record))->toBeEmpty();
})->with(['officer assessment', 'civilian']);

test('a failed log write sends no file', function (string $who) {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $record = $who === 'civilian' ? pestCivilianAssessment() : $w->assessmentA;
    $this->actingAs($admin);

    whilePdfLogWritesFail(function () use ($record) {
        $response = $this->get(pdfDownloadUrl($record));

        $response->assertServerError()
            ->assertHeaderMissing('Content-Disposition');

        // The body is the error page, not the file. (With APP_DEBUG on, that
        // page quotes source lines, so look at what the body is, not what it
        // mentions.)
        expect($response->headers->get('Content-Type'))->not->toBe('application/pdf')
            ->and(str_starts_with((string) $response->getContent(), '%PDF'))->toBeFalse();
    });

    expect(pdfDownloadRows($record))->toBeEmpty();
})->with(['officer assessment', 'civilian']);

// --- Where the button is ----------------------------------------------------------

test('Assessment review\'s View offers Download PDF, for police admins and admins', function (string $who) {
    $w = pestTwoAgencies();
    $user = $who === 'admin' ? staffUser(UserRole::Admin) : $w->policeAdminA;
    $this->actingAs($user);

    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA))
        ->assertActionVisible(TestAction::make('download_pdf')->table($w->assessmentA));
})->with(['police admin', 'admin']);

test('an officer assessment\'s follow-up page offers its PDF through the logged route', function () {
    $followUp = pestFollowUp(FollowUpKind::LawEnforcementAssessment);

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
        ->assertOk()
        ->assertSee(route('staff.assessments.pdf', $followUp->LawEnforcementDocumentID), false);
});
