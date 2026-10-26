<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;
use App\Services\AssessmentPdf;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
The civilian assessment PDF, and the log of who viewed or downloaded an
assessment (assessment_access_log).

Civilian assessments download as a PDF just as law-enforcement ones do
(CivilianAssessmentPdfController, /civilian-assessments/{id}/pdf), from the
same record template, and only by someone who can already see them: admins.
Every download of either kind, and every civilian View pop-up, writes one log
row - who, which record, when - because each puts the victim's safe phone
number in front of someone. Law-enforcement *views* are Connor's (team plan
task 9) and aren't logged yet.

App\Services\AssessmentPdf is swapped for a fake here, so a test can count
renders or make one fail; real printing is pinned in PdfRenderingTest.
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
    cleanupStaffData();
});

function accessRows(LawEnforcementAssessment | PrivateAssessment $record): Illuminate\Support\Collection
{
    $column = $record instanceof PrivateAssessment ? 'PrivateDocumentID' : 'LawEnforcementDocumentID';

    return AssessmentAccessLog::where($column, $record->getKey())->get();
}

test('an admin downloads a civilian assessment, and the download is logged', function () {
    $assessment = pestCivilianAssessment();
    $admin = staffUser(UserRole::Admin);

    $this->actingAs($admin)
        ->get(route('staff.civilian-assessments.pdf', $assessment->DocumentID))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="civilian-assessment-'.substr($assessment->DocumentID, 0, 8).'.pdf"');

    $rows = accessRows($assessment);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::Download)
        ->and($rows[0]->AccessedBy)->toBe($admin->getKey())
        ->and($rows[0]->LawEnforcementDocumentID)->toBeNull()
        ->and($rows[0]->AccessedAt)->not->toBeNull();
});

// Other roles get a 404, as if the record didn't exist. An inactive account
// is stopped earlier, by EnsureStaffPanelAccess, with a 403.
test('nobody but an admin can download a civilian assessment, and a refusal logs nothing', function (Closure $makeUser, int $status) {
    $assessment = pestCivilianAssessment();

    $this->actingAs($makeUser())
        ->get(route('staff.civilian-assessments.pdf', $assessment->DocumentID))
        ->assertStatus($status);

    expect(accessRows($assessment))->toBeEmpty();
})->with([
    'secretary' => [fn () => staffUser(UserRole::Secretary), 404],
    'police admin' => [fn () => staffUser(UserRole::PoliceAdmin), 404],
    'officer' => [fn () => staffUser(UserRole::LawEnforcement), 404],
    'inactive admin' => [fn () => staffUser(UserRole::Admin, isActive: false), 403],
]);

test('an anonymous visitor is sent to sign in for a civilian PDF', function () {
    $assessment = pestCivilianAssessment();

    $this->get(route('staff.civilian-assessments.pdf', $assessment->DocumentID))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
});

test('a law-enforcement PDF download is logged, and a refused one is not', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->officerA)
        ->get(route('staff.assessments.pdf', $w->assessmentA->DocumentID))
        ->assertOk();

    $this->get(route('staff.assessments.pdf', $w->assessmentB->DocumentID))
        ->assertNotFound();

    $rows = accessRows($w->assessmentA);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::Download)
        ->and($rows[0]->AccessedBy)->toBe($w->officerA->getKey())
        ->and(accessRows($w->assessmentB))->toBeEmpty();
});

test('opening a civilian assessment\'s View pop-up is logged, with a Download PDF button', function () {
    $assessment = pestCivilianAssessment();
    $admin = staffUser(UserRole::Admin);

    $this->actingAs($admin);

    Livewire::test(ListCivilianAssessments::class)
        ->mountAction(TestAction::make('view')->table($assessment))
        ->assertMountedActionModalSee('(423)555-0100')
        ->assertActionVisible(TestAction::make('download_pdf')->table($assessment));

    $rows = accessRows($assessment);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::View)
        ->and($rows[0]->AccessedBy)->toBe($admin->getKey());
});

test('the civilian PDF holds the whole record, submitter included', function () {
    $submitter = SubmitterInfo::create([
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Sam',
        'SubmitterLastName' => 'Reporter',
        'SubmitterEmail' => 'sam@pest.test',
        'SubmitterPhoneNumber' => '423-555-0199',
    ]);
    $assessment = pestCivilianAssessment([1, 4], $submitter)->load(['assessmentAnswers', 'submitterInfo']);

    $html = App\Support\RecordPdf::html($assessment);

    expect($html)
        ->toContain('Civilian submission')
        ->toContain('Submitted by')
        ->toContain(STAFF_TEST_PREFIX.'Sam Reporter')
        ->toContain('sam@pest.test')
        ->toContain('(423)555-0199')
        ->toContain(STAFF_TEST_PREFIX.'Victim Doe')
        ->toContain('(423)555-0100')
        ->toContain('Test Offender')
        ->toContain('Spouse')
        ->toContain('2 of 11 yes')
        ->not->toContain('Officer:');

    foreach (App\Filament\Pages\NewAssessment::QUESTIONS as $question) {
        expect($html)->toContain(e($question));
    }
});

test('an anonymous civilian PDF says so, and the officer PDF is unchanged', function () {
    $anonymous = pestCivilianAssessment()->load(['assessmentAnswers', 'submitterInfo']);

    expect(App\Support\RecordPdf::html($anonymous))
        ->toContain('Anonymous');

    $w = pestTwoAgencies();
    $officerPdf = App\Support\RecordPdf::html($w->assessmentA->load(['assessmentAnswers', 'submitter']));

    expect($officerPdf)
        ->toContain('Officer: '.$w->officerA->name)
        // The label, not the PDF stylesheet's comment that names the card.
        ->not->toContain('Submitted by</div>')
        ->not->toContain('Civilian submission');
});

test('a log row points at exactly one assessment and is never changed', function () {
    $w = pestTwoAgencies();
    $civilian = pestCivilianAssessment();
    $row = AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::View, $w->officerA);

    // Neither, or both: refused by the database itself.
    foreach ([[null, null], [$w->assessmentA->getKey(), $civilian->getKey()]] as [$le, $private]) {
        expect(fn () => DB::connection('Portal')->table('assessment_access_log')->insert([
            'AccessID' => (string) Illuminate\Support\Str::uuid(),
            'LawEnforcementDocumentID' => $le,
            'PrivateDocumentID' => $private,
            'Action' => 'view',
            'AccessedBy' => $w->officerA->getKey(),
        ]))->toThrow(QueryException::class);
    }

    expect(fn () => $row->update(['Action' => AssessmentAccess::Download]))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});
