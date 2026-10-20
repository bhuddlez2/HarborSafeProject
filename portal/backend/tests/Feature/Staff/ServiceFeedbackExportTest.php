<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Models\FeedbackScan;
use App\Models\ServiceFeedback;
use App\Services\ServiceFeedbackExport;
use App\Support\Timezones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

/*
The service feedback list export (team plan task 34): Harbor Safe filters the
Service feedback table by date submitted and service, then downloads the list
as Excel or PDF for grant-giving organisations. The file holds exactly the
forms the filters leave - every page of them - one form per row, and only
what each form holds: the day submitted (Eastern), service, rating, comment.
No filters, export date, source or staff names. Exports are not logged.

ServiceFeedbackExport::printPdf() is overridden here: a real render launches
headless Chromium (see AssessmentPdfAccessTest).
*/

beforeEach(function () {
    $this->instance(ServiceFeedbackExport::class, new class extends ServiceFeedbackExport
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
    cleanupPublicFormData();
    cleanupStaffData();
});

// An online form, written as the public endpoint would. $utc is the stored
// SubmissionDate.
function exportOnlineForm(int $serviceId, string $utc, int $rating = 5, ?string $comment = null): void
{
    DB::connection('Feedback')->table('service_feedback')->insert([
        'FormID' => (string) Str::uuid(),
        'ServiceID' => $serviceId,
        'Rating' => $rating,
        'Comment' => $comment,
        'SubmissionDate' => $utc,
    ]);
}

// The downloaded file's bytes, out of Livewire's download effect.
function exportedBytes($livewire): string
{
    return base64_decode(data_get($livewire->effects, 'download.content'));
}

// Every row of the downloaded .xlsx, header first, as plain values.
function exportedSheet(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'feedback-test');
    file_put_contents($path, $bytes);

    $reader = new Reader;
    $reader->open($path);

    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(
                fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : $value,
                $row->toArray(),
            );
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

function exportFileName(string $extension): string
{
    return 'service-feedback-'.now(Timezones::DISPLAY)->format('Y-m-d').'.'.$extension;
}

test('the Excel export holds every form the filters leave, one per row: date, service, rating, comment', function (UserRole $role) {
    $this->actingAs(staffUser($role));
    $secretary = staffUser(UserRole::Secretary);

    $counseling = publicFormsMakeService(staffRunToken().' Counseling');
    $shelter = publicFormsMakeService(staffRunToken().' Shelter');

    // In range, Counseling: an online form and a staff entry with a scan.
    exportOnlineForm($counseling, '2026-03-15 15:00:00', 5, 'Very helpful.');
    ServiceFeedback::recordStaffEntry([
        'ServiceID' => $counseling,
        'Rating' => 3,
        'Comment' => 'Filled in at the front desk.',
        'SubmissionDate' => '2026-03-20 13:15:00',
        'ScanFileID' => FeedbackScan::store('%PDF-1.4 scan', staffRunToken().'-scan.pdf', 'application/pdf')->getKey(),
    ], $secretary);

    // Left out: Counseling after the range, and Shelter inside it.
    exportOnlineForm($counseling, '2026-04-02 15:00:00', 1, 'Outside the dates.');
    exportOnlineForm($shelter, '2026-03-16 15:00:00', 2, 'Another service.');

    $livewire = Livewire::test(ListServiceFeedback::class)
        ->filterTable('date_submitted', ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->filterTable('ServiceID', $counseling)
        ->callAction('exportExcel')
        ->assertFileDownloaded(exportFileName('xlsx'));

    // Newest first, as the table sorts; the day submitted, in Eastern. Nothing
    // about how each form arrived: the staff entry carries no "Staff", no
    // name, no scan.
    expect(exportedSheet(exportedBytes($livewire)))->toBe([
        ['Submitted', 'Service', 'Rating (1-5)', 'Comment'],
        ['2026-03-20 00:00', staffRunToken().' Counseling', 3, 'Filled in at the front desk.'],
        ['2026-03-15 00:00', staffRunToken().' Counseling', 5, 'Very helpful.'],
    ]);
})->with([UserRole::Admin, UserRole::Secretary]);

test('the export holds every page of the list, not just the one on screen', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $service = publicFormsMakeService(staffRunToken().' Counseling');

    foreach (range(1, 14) as $day) {
        exportOnlineForm($service, sprintf('2026-05-%02d 15:00:00', $day));
    }

    $livewire = Livewire::test(ListServiceFeedback::class)
        ->filterTable('ServiceID', $service)
        ->callAction('exportExcel');

    // A header plus all 14 forms, though the table shows 10 a page.
    expect(exportedSheet(exportedBytes($livewire)))->toHaveCount(15);
});

test('the date range is in Eastern days', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $service = publicFormsMakeService(staffRunToken().' Counseling');

    // 11:30 pm Eastern on 9 March: before the range.
    exportOnlineForm($service, '2026-03-10 03:30:00', 1, 'Evening of the 9th.');
    // 12:30 am Eastern on 10 March: the first minutes of the range.
    exportOnlineForm($service, '2026-03-10 04:30:00', 2, 'Early on the 10th.');
    // 10 pm Eastern on 31 March, already 1 April in UTC: still in range.
    exportOnlineForm($service, '2026-04-01 02:00:00', 3, 'Late on the 31st.');

    $livewire = Livewire::test(ListServiceFeedback::class)
        ->filterTable('date_submitted', ['from' => '2026-03-10', 'until' => '2026-03-31'])
        ->filterTable('ServiceID', $service)
        ->callAction('exportExcel');

    $rows = array_slice(exportedSheet(exportedBytes($livewire)), 1);

    expect(array_column($rows, 3))->toBe(['Late on the 31st.', 'Early on the 10th.'])
        // The Eastern day, not UTC's: 10 pm on the 31st is still the 31st.
        ->and(array_column($rows, 0))->toBe(['2026-03-31 00:00', '2026-03-10 00:00']);
});

test('the PDF export lists the same forms, and nothing but what they say', function () {
    $admin = staffUser(UserRole::Admin);
    $this->actingAs($admin);

    $counseling = publicFormsMakeService(staffRunToken().' Counseling');
    $shelter = publicFormsMakeService(staffRunToken().' Shelter');

    exportOnlineForm($counseling, '2026-03-15 15:00:00', 5, 'Very helpful.');
    exportOnlineForm($shelter, '2026-03-16 15:00:00', 2, 'Another service.');

    Livewire::test(ListServiceFeedback::class)
        ->filterTable('date_submitted', ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->filterTable('ServiceID', $counseling)
        ->callAction('exportPdf')
        ->assertFileDownloaded(exportFileName('pdf'), '%PDF-fake');

    $html = app(ServiceFeedbackExport::class)::$html;

    expect($html)
        ->toContain('2026-03-15')
        ->toContain(staffRunToken().' Counseling')
        ->toContain('Very helpful.')
        ->not->toContain('Another service.')
        // The day only - no time, filters, export date, source or names.
        ->not->toContain('11:00')
        ->not->toContain('Filters')
        ->not->toContain('Submitted from')
        ->not->toContain('Exported')
        ->not->toContain('Online')
        ->not->toContain($admin->name);

    foreach (ServiceFeedbackExport::COLUMNS as $column) {
        expect($html)->toContain(e($column));
    }
});

test('an empty list downloads nothing and says why', function (string $action) {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ListServiceFeedback::class)
        ->filterTable('ServiceID', publicFormsMakeService(staffRunToken().' Unused'))
        ->callAction($action)
        ->assertNoFileDownloaded()
        ->assertNotified('No feedback matches these filters');
})->with(['exportExcel', 'exportPdf']);
