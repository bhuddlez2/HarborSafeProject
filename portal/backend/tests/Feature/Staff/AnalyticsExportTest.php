<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Analytics\Pages\CivilianAssessmentAnalytics;
use App\Filament\Clusters\Analytics\Pages\FeedbackAnalytics;
use App\Filament\Clusters\Analytics\Pages\FollowUpAnalytics;
use App\Filament\Clusters\Analytics\Pages\OfficerAssessmentAnalytics;
use App\Services\Analytics\AnalyticsExport;
use App\Support\Timezones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

/*
Analytics export (Burchfield B68, AS 2026-10-28): each tab's Export button
downloads that tab as Excel or PDF, for the applied date range and filter,
with the range, the filter (assessment tabs) and the export date at the top.
Totals only, built from the same sections() the page draws; not logged.

AnalyticsExport::printPdf() is overridden to read the HTML; the PDF itself
prints through App\Support\HtmlPdf like every other (PdfRenderingTest).
Fixtures sit in March 2003, the month AnalyticsTest uses, since the suite
shares the local database.
*/

beforeEach(function () {
    $this->instance(AnalyticsExport::class, new class extends AnalyticsExport
    {
        public static string $html = '';

        protected function printPdf(string $html): string
        {
            static::$html = $html;

            return '%PDF-fake';
        }
    });
});

afterEach(function () {
    cleanupStaffData();
    cleanupPublicFormData();
});

function analyticsExportFileName(string $tab, string $extension): string
{
    return "analytics-{$tab}-".now(Timezones::DISPLAY)->format('Y-m-d').'.'.$extension;
}

// Every row of the downloaded .xlsx as plain values, trailing empty cells dropped.
function analyticsExportSheet($livewire): array
{
    $path = tempnam(sys_get_temp_dir(), 'analytics-test');
    file_put_contents($path, base64_decode(data_get($livewire->effects, 'download.content')));

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $values = $row->toArray();

            while ($values !== [] && end($values) === '') {
                array_pop($values);
            }

            $rows[] = $values;
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

// The first row whose first cell is $label.
function analyticsExportRow(array $rows, string $label): ?array
{
    foreach ($rows as $row) {
        if (($row[0] ?? null) === $label) {
            return $row;
        }
    }

    return null;
}

function analyticsExportMarch2003($livewire)
{
    return $livewire
        ->call('setRange', 'custom')
        ->set('customFrom', '2003-03-01')
        ->set('customTo', '2003-03-31')
        ->call('applyCustomRange')
        ->assertHasNoErrors();
}

test('Excel holds the tab as shown: range, export date, tiles and charts, with number cells', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $service = publicFormsMakeService(staffRunToken().' Counseling');

    foreach ([[5, '2003-03-10 15:00:00'], [3, '2003-03-20 15:00:00']] as [$rating, $utc]) {
        DB::connection('Feedback')->table('service_feedback')->insert([
            'FormID' => (string) Str::uuid(),
            'ServiceID' => $service,
            'Rating' => $rating,
            'Comment' => 'Pest analytics export comment',
            'SubmissionDate' => $utc,
        ]);
    }

    $livewire = analyticsExportMarch2003(Livewire::test(FeedbackAnalytics::class))
        ->callAction('exportExcel')
        ->assertFileDownloaded(analyticsExportFileName('feedback-and-resource-requests', 'xlsx'));

    $rows = analyticsExportSheet($livewire);

    expect($rows[0])->toBe(['Feedback and Resource Requests'])
        ->and($rows[1])->toBe(['Date range: Mar 1 – Mar 31, 2003, Eastern time'])
        // No sex filter on this tab, so no filter line.
        ->and($rows[2])->toBe(['Exported '.now(Timezones::DISPLAY)->format('M j, Y')])
        ->and(analyticsExportRow($rows, 'Feedback forms received'))->toBe(['Feedback forms received', 2, 'Mar 1 – Mar 31, 2003'])
        ->and(analyticsExportRow($rows, 'Average rating'))->toBe(['Average rating', '4.0 / 5', 'across every service'])
        // A chart row: its count as a number, and the page's own text where it says more.
        ->and(analyticsExportRow($rows, staffRunToken().' Counseling'))->toBe([staffRunToken().' Counseling', 4, '4.0 / 5'])
        ->and(analyticsExportRow($rows, 'Rating 5'))->toBe(['Rating 5', 1])
        // Totals only: never a comment.
        ->and(json_encode($rows))->not->toContain('Pest analytics export comment');
});

test('the PDF carries the applied range and filter, and no names or phone numbers', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Export PD'));
    $assessment = pestOfficerAssessment($officer, 'ExportVictim');
    DB::connection('Portal')->table('law_enforcement_assessment')
        ->where('DocumentID', $assessment->DocumentID)
        ->update(['DateCreated' => '2003-03-12 15:00:00']);

    $this->actingAs(staffUser(UserRole::Admin));

    $livewire = analyticsExportMarch2003(Livewire::test(OfficerAssessmentAnalytics::class));

    $livewire->callAction('exportPdf')
        ->assertFileDownloaded(analyticsExportFileName('law-enforcement-assessments', 'pdf'), '%PDF-fake');

    $html = app(AnalyticsExport::class)::$html;
    expect($html)->toContain('Law-Enforcement Assessments')
        ->toContain('Date range: Mar 1 – Mar 31, 2003, Eastern time')
        ->toContain('Filter: None')
        ->toContain('Exported '.now(Timezones::DISPLAY)->format('M j, Y'))
        ->toContain(e(staffRunToken().' Export PD'))
        ->toContain('class="hs-an-bar" style="width: 100%"')
        ->not->toContain('ExportVictim')
        ->not->toContain('555-0100');

    $livewire->set('draftVictimSex', 'F')
        ->call('applySexFilter')
        ->callAction('exportPdf');

    expect(app(AnalyticsExport::class)::$html)->toContain('Filter: Victim: Female · Offender: Any');
});

test('a preset range is named with its dates', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $lines = Livewire::test(FollowUpAnalytics::class)
        ->call('setRange', '3m')
        ->instance()
        ->exportLines();

    expect($lines[0])->toStartWith('Date range: Last 3 months (')
        ->and($lines[0])->toEndWith('), Eastern time')
        ->and($lines)->toHaveCount(2);
});

test('every tab offers Export to whoever may open it', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    foreach ([OfficerAssessmentAnalytics::class, CivilianAssessmentAnalytics::class, FeedbackAnalytics::class, FollowUpAnalytics::class] as $page) {
        Livewire::test($page)
            ->assertActionVisible('exportExcel')
            ->assertActionVisible('exportPdf');
    }
});

test('numbers are written as numbers only when they are plain numbers', function () {
    expect(AnalyticsExport::tileValue('1,234'))->toBe(1234)
        ->and(AnalyticsExport::tileValue('2.3'))->toBe(2.3)
        ->and(AnalyticsExport::tileValue('2 (67%)'))->toBe('2 (67%)')
        ->and(AnalyticsExport::tileValue('—'))->toBe('—')
        ->and(AnalyticsExport::barValue(['value' => 0.0, 'display' => '—']))->toBe('')
        ->and(AnalyticsExport::barValue(['value' => 3.0, 'display' => '3']))->toBe(3)
        ->and(AnalyticsExport::shownAs(['value' => 1200.0, 'display' => '1,200']))->toBeNull()
        ->and(AnalyticsExport::shownAs(['value' => 5.0, 'display' => '5 · 40%']))->toBe('5 · 40%');
});
