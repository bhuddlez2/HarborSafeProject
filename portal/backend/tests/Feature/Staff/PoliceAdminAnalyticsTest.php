<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Analytics\Pages\OfficerAssessmentAnalytics;
use App\Filament\Pages\AgencyAnalytics;
use App\Models\LawEnforcementAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Services\Analytics\AnalyticsExport;
use App\Support\HighRisk;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

/*
Analytics for police admins (#67): App\Filament\Pages\AgencyAnalytics shows
their own agency's law-enforcement assessments only - every number through
LawEnforcementAssessment::visibleTo() (AssessmentStats::officer() with a
viewer) - with no "By agency" chart, and exports the same way. Harbor Safe's
own Law-Enforcement Assessments tab is unchanged.

Fixtures sit in March 2003, the month AnalyticsTest uses, since the suite
shares the local database: agency A has three assessments (two high risk),
agency B two, plus a civilian assessment, a resource request and a feedback
form in the same month that must never count.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// An officer assessment moved into March 2003 with the given yes answers.
// Query builder: the models refuse updates outside AssessmentEditor.
function pestMarchAssessment(App\Models\User $officer, string $victim, array $yes): LawEnforcementAssessment
{
    $assessment = pestOfficerAssessment($officer, $victim);
    $portal = DB::connection('Portal');

    $portal->table('law_enforcement_assessment')
        ->where('DocumentID', $assessment->DocumentID)
        ->update(['DateCreated' => '2003-03-12 15:00:00']);

    $portal->table('_assessment_answers')
        ->where('AssessmentDocID', $assessment->AssessmentDocID)
        ->update(collect(range(1, 11))->mapWithKeys(fn (int $i): array => ["RiskIndicator{$i}" => in_array($i, $yes, true)])->all());

    return $assessment;
}

function pestAnalyticsWorld(): object
{
    $w = pestTwoAgencies();

    // pestTwoAgencies()' own two are dated today, outside the range.
    $w->a = [
        pestMarchAssessment($w->officerA, 'AnalyticsA1', [1]),        // high risk (Q1)
        pestMarchAssessment($w->officerA, 'AnalyticsA2', [4, 5, 6, 7]), // high risk (4 yes)
        pestMarchAssessment($w->officerA, 'AnalyticsA3', [5]),        // not high risk
    ];
    $w->b = [
        pestMarchAssessment($w->officerB, 'AnalyticsB1', [1, 2]),
        pestMarchAssessment($w->officerB, 'AnalyticsB2', [3]),
    ];

    // Never counted on a police admin's page: not law-enforcement assessments.
    $civilian = pestCivilianAssessment([1, 2, 3]);
    DB::connection('Portal')->table('_private_assessment')->where('DocumentID', $civilian->DocumentID)
        ->update(['DateCreated' => '2003-03-12 15:00:00']);

    $request = ResourceRequestForm::create(['FirstName' => publicFormsRunToken(), 'LastName' => 'Analytics', 'EmailAddress' => 'analytics@example.com']);
    $feedback = ServiceFeedback::create(['ServiceID' => publicFormsMakeService(staffRunToken().' Analytics'), 'Rating' => 5, 'Comment' => 'Fine.']);
    $feedbackDb = DB::connection('Feedback');
    $feedbackDb->table('resource_request_form')->where('FormID', $request->FormID)->update(['SubmissionDate' => '2003-03-12 15:00:00']);
    $feedbackDb->table('service_feedback')->where($feedback->getKeyName(), $feedback->getKey())->update(['SubmissionDate' => '2003-03-12 15:00:00']);

    return $w;
}

function pestMarch2003($livewire)
{
    return $livewire
        ->call('setRange', 'custom')
        ->set('customFrom', '2003-03-01')
        ->set('customTo', '2003-03-31')
        ->call('applyCustomRange')
        ->assertHasNoErrors();
}

// The downloaded .xlsx as rows of plain values.
function pestAnalyticsSheet($livewire): array
{
    $path = tempnam(sys_get_temp_dir(), 'agency-analytics-test');
    file_put_contents($path, base64_decode(data_get($livewire->effects, 'download.content')));

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

function pestSheetRow(array $rows, string $label): ?array
{
    return collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === $label);
}

// Captures the PDF's HTML instead of printing it.
function pestCapturePdf(): void
{
    test()->instance(AnalyticsExport::class, new class extends AnalyticsExport
    {
        public static string $html = '';

        protected function printPdf(string $html): string
        {
            static::$html = $html;

            return '%PDF-fake';
        }
    });
}

test('the page counts only the police admin\'s own agency\'s law-enforcement assessments', function () {
    $w = pestAnalyticsWorld();
    $this->actingAs($w->policeAdminA);

    $expectedHighRisk = collect($w->a)
        ->filter(fn (LawEnforcementAssessment $a): bool => HighRisk::of($a->fresh()->assessmentAnswers))
        ->count();
    expect($expectedHighRisk)->toBe(2);

    pestMarch2003(Livewire::test(AgencyAnalytics::class))
        ->assertOk()
        ->assertSee('Assessments submitted')
        ->assertSee('2 (67%)')        // high risk: 2 of 3
        ->assertDontSee('By Agency')
        ->assertDontSee(staffRunToken().' Agency B');

    // Agency B's police admin sees their own two, both high risk.
    $this->actingAs($w->policeAdminB);
    pestMarch2003(Livewire::test(AgencyAnalytics::class))->assertSee('2 (100%)');
});

test('the Excel export holds the same scoped numbers, its agency, and no by-agency section', function () {
    $w = pestAnalyticsWorld();
    $this->actingAs($w->policeAdminA);

    $rows = pestAnalyticsSheet(pestMarch2003(Livewire::test(AgencyAnalytics::class))->callAction('exportExcel'));

    expect(pestSheetRow($rows, 'Assessments submitted')[1])->toBe(3)
        ->and(pestSheetRow($rows, 'High risk')[1])->toBe('2 (67%)')
        ->and(pestSheetRow($rows, 'Agency: '.staffRunToken().' Agency A'))->not->toBeNull()
        ->and(pestSheetRow($rows, 'By Agency'))->toBeNull()
        ->and(json_encode($rows))->not->toContain('Agency B');
});

test('the PDF export holds the same scoped numbers, no by-agency section, in black and white', function () {
    $w = pestAnalyticsWorld();
    pestCapturePdf();
    $this->actingAs($w->policeAdminA);

    pestMarch2003(Livewire::test(AgencyAnalytics::class))->callAction('exportPdf');
    $html = app(AnalyticsExport::class)::$html;

    expect($html)->toContain('Agency: '.e(staffRunToken().' Agency A'))
        ->toContain('2 (67%)')
        ->not->toContain('By Agency')
        ->not->toContain('Agency B');

    expectBlackAndWhite($html);
});

test('both pages download real Excel and PDF files', function (string $page, Closure $viewer) {
    $this->actingAs($viewer());

    $excel = Livewire::test($page)->callAction('exportExcel');
    $pdf = Livewire::test($page)->callAction('exportPdf');

    expect(substr(base64_decode(data_get($excel->effects, 'download.content')), 0, 2))->toBe('PK')
        ->and(substr(base64_decode(data_get($pdf->effects, 'download.content')), 0, 5))->toBe('%PDF-');
})->with([
    'police admin' => [AgencyAnalytics::class, fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Download PD'))],
    'admin' => [OfficerAssessmentAnalytics::class, fn () => staffUser(UserRole::Admin)],
]);

test('Harbor Safe\'s own tab is unchanged: every agency, with By agency', function () {
    $w = pestAnalyticsWorld();
    $this->actingAs(staffUser(UserRole::Admin));

    pestMarch2003(Livewire::test(OfficerAssessmentAnalytics::class))
        ->assertSee('By Agency')
        ->assertSee(staffRunToken().' Agency A')
        ->assertSee(staffRunToken().' Agency B');

    $rows = pestAnalyticsSheet(pestMarch2003(Livewire::test(OfficerAssessmentAnalytics::class))->callAction('exportExcel'));

    expect(pestSheetRow($rows, 'By Agency'))->not->toBeNull()
        ->and(pestSheetRow($rows, staffRunToken().' Agency A')[1])->toBe(3)
        ->and(pestSheetRow($rows, staffRunToken().' Agency B')[1])->toBe(2);
});

test('only a police admin with an agency may open it', function (Closure $makeUser) {
    $this->actingAs($makeUser());

    expect(AgencyAnalytics::canAccess())->toBeFalse();
    $this->get('/police/analytics')->assertForbidden();
})->with([
    'police admin without an agency' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, null)],
    'officer' => [fn () => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Refused PD'))],
    'secretary' => [fn () => staffUser(UserRole::Secretary)],
    'admin' => [fn () => staffUser(UserRole::Admin)],
    'super admin' => [fn () => staffUser(UserRole::SuperAdmin)],
]);

test('a police admin with an agency opens it, and not Harbor Safe\'s Analytics', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Open PD')));

    $this->get('/police/analytics')->assertOk();
    $this->get('/analytics/officer-assessments')->assertForbidden();
});
