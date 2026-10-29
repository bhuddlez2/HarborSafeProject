<?php

use App\Enums\FollowUpKind;
use App\Enums\UserRole;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Services\Analytics\AnalyticsExport;
use App\Services\AssessmentPdf;
use App\Services\ServiceFeedbackExport;
use App\Support\RecordPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/*
Every PDF the app makes is black and white (#44): a white page, black text,
black lines, nothing that needs colour to be understood. Only the PDFs: the
screens, theme.css and the Excel exports keep their colours.

Checked on the HTML each PDF is printed from - the record PDFs through a fake
AssessmentPdf that keeps render()'s full signature and stores what
RecordPdf would print; the two list exports through their printPdf() - and on
the colours the two exports draw straight onto each page (headings, page
numbers), through a recording canvas.
*/

beforeEach(function () {
    $this->instance(AssessmentPdf::class, new class extends AssessmentPdf
    {
        public static string $html = '';

        public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
        {
            static::$html = RecordPdf::html($record);

            return '%PDF-fake';
        }

        public function renderWithLog(FollowUp $followUp): string
        {
            static::$html = RecordPdf::htmlWithLog($followUp);

            return '%PDF-fake';
        }
    });
});

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// The colours the panel uses on screen, none of which may reach a PDF.
const PDF_SCREEN_COLOURS = [
    '#5c0f8b', '#9068b8', '#cfb7e2', '#eadcf5', '#a77fc6', '#c3a6da', '#ede3f5', '#f5f0fb', '#c9c1d6',
    '#ebdff4', '#3b2a4d', '#d8cfe3', '#f3f0f7', '#1b1726',
    '#6b7280', '#374151', '#4b5563', '#9ca3af', '#e5e7eb', '#f3f4f6', '#f9fafb',
    '#b91c1c', '#fee2e2', '#fef2f2', '#fff5f5', '#b45309', '#fffbeb', '#15803d', '#f0fdf4',
    '#f59e0b', '#92400e',
];

// Fails unless $html uses no colour but black and white.
function expectBlackAndWhite(string $html): void
{
    // Hex colours; "&#039;" and the like are escaped characters, not colours.
    preg_match_all('/(?<![&\w])#([0-9a-fA-F]{3,8})\b/', $html, $hex);

    $others = collect($hex[1])
        ->map(fn (string $value): string => strtolower($value))
        ->reject(fn (string $value): bool => in_array($value, ['000', '000000', 'fff', 'ffffff'], true))
        ->unique()
        ->values()
        ->all();

    expect($others)->toBe([], 'Colours other than black and white: #'.implode(', #', $others));

    expect($html)->not->toMatch('/\b(rgba?|hsla?)\s*\(/i');

    // Named colours in the declarations that carry one: color, background,
    // and border with its sides (not border-collapse, -spacing or -radius).
    preg_match_all('/(?<![\w-])(?:color|background(?:-color)?|border(?:-(?:top|right|bottom|left))?(?:-color)?)\s*:\s*([^;}"]+)/i', $html, $declarations);

    foreach ($declarations[1] as $value) {
        $words = preg_replace('/#[0-9a-fA-F]{3,8}\b|[\d.]+(pt|px|mm|em|%)?|\b(solid|none|transparent|black|white|inherit|dashed|dotted)\b/i', '', $value);

        expect(trim($words))->toBe('', "A colour declaration other than black and white: {$value}");
    }

    foreach (PDF_SCREEN_COLOURS as $colour) {
        expect(stripos($html, $colour))->toBeFalse("Screen colour {$colour} in a PDF");
    }
}

// A canvas that records every colour drawn on it.
function recordingCanvas(array &$colours): Canvas
{
    $canvas = Mockery::mock(Canvas::class);
    $canvas->shouldReceive('get_width')->andReturn(792.0);
    $canvas->shouldReceive('get_height')->andReturn(612.0);
    $canvas->shouldReceive('text')->andReturnUsing(function (...$args) use (&$colours): void {
        $colours[] = ['text', $args[5]];
    });
    $canvas->shouldReceive('line')->andReturnUsing(function (...$args) use (&$colours): void {
        $colours[] = ['line', $args[4]];
    });
    $canvas->shouldReceive('filled_rectangle')->andReturnUsing(function (...$args) use (&$colours): void {
        $colours[] = ['filled_rectangle', $args[4]];
    });

    return $canvas;
}

function recordingFonts(): FontMetrics
{
    $fonts = Mockery::mock(FontMetrics::class);
    $fonts->shouldReceive('getFont')->andReturn('DejaVuSans');
    $fonts->shouldReceive('getTextWidth')->andReturn(20.0);

    return $fonts;
}

test('an officer assessment PDF is black and white, with yes answers and high risk in words', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('BW PD'));
    // pestOfficerAssessment answers yes to question 1: high risk.
    $assessment = pestOfficerAssessment($officer, 'BWVictim', ['OffenderVictimRelationship' => 'spouse']);

    $this->actingAs($officer)
        ->get(route('staff.assessments.pdf', $assessment->DocumentID))
        ->assertOk();

    $html = app(AssessmentPdf::class)::$html;

    expect($html)->toContain('BWVictim')
        ->toContain('High risk')
        ->toContain('hs-ar-indicator--yes');

    expectBlackAndWhite($html);
});

test('a civilian assessment PDF is black and white', function () {
    $assessment = pestCivilianAssessment([1, 2, 5]);

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(route('staff.civilian-assessments.pdf', $assessment->DocumentID))
        ->assertOk();

    expectBlackAndWhite(app(AssessmentPdf::class)::$html);
});

test('a resource request PDF is black and white', function () {
    $resource = publicFormsMakeResource(publicFormsRunToken().' BW Shelter');

    $formId = $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => publicFormsEmail(),
        'SafePhoneNumber' => '(423)555-0177',
        'ResourceTypeIDs' => [$resource],
        'Message' => 'Please call after 5.',
    ])->assertCreated()->json('FormID');

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(route('staff.resource-requests.pdf', $formId))
        ->assertOk();

    $html = app(AssessmentPdf::class)::$html;

    expect($html)->toContain('BW Shelter')->toContain('Please call after 5.');

    expectBlackAndWhite($html);
});

test('a service feedback form PDF is black and white', function () {
    foreach ([1, 3, 5] as $rating) {
        $form = (new ServiceFeedback)->forceFill([
            'SubmissionDate' => now(),
            'Rating' => $rating,
            'Comment' => 'Kind staff.',
        ]);

        expectBlackAndWhite(RecordPdf::html($form));
    }
});

test('the follow-up PDF with its log is black and white', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(route('staff.follow-ups.pdf-with-log', $followUp->FollowUpID))
        ->assertOk();

    $html = app(AssessmentPdf::class)::$html;

    expect($html)->toContain('Internal: includes follow-up notes');

    expectBlackAndWhite($html);
});

test('the screens keep their colours', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('BW Screen PD'));
    $assessment = pestOfficerAssessment($officer, 'BWScreen');

    // The View pop-up's partial on its own, without isPdf: the red high-risk score.
    $screen = view(RecordPdf::ASSESSMENT, ['getRecord' => fn () => $assessment])->render();

    expect($screen)->toContain('#b91c1c')->not->toContain('High risk');
});

test('the service feedback list PDF is black and white, its drawn headings included', function () {
    $export = new class extends ServiceFeedbackExport
    {
        public static string $html = '';

        protected function printPdf(string $html): string
        {
            static::$html = $html;

            return '%PDF-fake';
        }

        public function drawPage(Canvas $canvas, FontMetrics $fonts, int $page, int $pages): void
        {
            $this->decoratePage($canvas, $fonts, $page, $pages);
        }
    };

    $forms = collect([
        (new ServiceFeedback)->forceFill(['SubmissionDate' => now(), 'Rating' => 5, 'Comment' => 'Very helpful.']),
        (new ServiceFeedback)->forceFill(['SubmissionDate' => now(), 'Rating' => 1, 'Comment' => str_repeat('A long comment. ', 120)]),
    ]);

    $export->pdf($forms);

    expect($export::$html)->toContain('Very helpful.');
    expectBlackAndWhite($export::$html);

    // Page 2 draws the column headings and the page number onto the canvas.
    $colours = [];
    $export->drawPage(recordingCanvas($colours), recordingFonts(), 2, 3);

    expect($colours)->not->toBeEmpty()
        ->and(collect($colours)->where(0, 'text')->count())->toBe(count(ServiceFeedbackExport::COLUMNS) + 1)
        ->and(collect($colours)->where(0, 'filled_rectangle')->all())->toBe([]);

    foreach ($colours as [$call, $colour]) {
        expect($colour)->toBe([0, 0, 0], "{$call} drawn in colour");
    }
});

test('the analytics PDF is black and white, its bars and page numbers included', function () {
    $export = new class extends AnalyticsExport
    {
        public static string $html = '';

        protected function printPdf(string $html): string
        {
            static::$html = $html;

            return '%PDF-fake';
        }

        public function drawPage(Canvas $canvas, FontMetrics $fonts, int $page, int $pages): void
        {
            $this->decoratePage($canvas, $fonts, $page, $pages);
        }
    };

    $export->pdf('Law-Enforcement Assessments', ['Date range: Mar 1 – Mar 31, 2003', 'Filter: None'], [[
        'heading' => 'Totals',
        'tiles' => [
            ['label' => 'Assessments', 'value' => '12', 'sub' => '4 high risk'],
            ['label' => 'High risk', 'value' => '4 (33%)', 'sub' => null],
        ],
        'cards' => [
            [
                'title' => 'By agency', 'note' => 'Each officer’s current agency.', 'type' => 'bars', 'footnote' => 'Totals only.',
                'bars' => [
                    ['name' => 'First PD', 'value' => 8, 'pct' => 100, 'display' => '8'],
                    ['name' => 'Second PD', 'value' => 4, 'pct' => 50, 'display' => '4'],
                ],
            ],
            [
                'title' => 'By month', 'note' => null, 'type' => 'table', 'footnote' => null,
                'firstColumn' => 'Month', 'columns' => ['High risk', 'Total'],
                'rows' => [['label' => 'March', 'values' => [4, 12]]],
            ],
        ],
    ]]);

    $html = $export::$html;

    // Every bar keeps its name and printed value beside it, so it reads without colour.
    expect($html)->toContain('class="hs-an-bar" style="width: 100%"')
        ->toContain('First PD')->toContain('>8<')
        ->toContain('Second PD')->toContain('>4<');

    expectBlackAndWhite($html);

    $colours = [];
    $export->drawPage(recordingCanvas($colours), recordingFonts(), 1, 2);

    expect($colours)->not->toBeEmpty();

    foreach ($colours as [$call, $colour]) {
        expect($colour)->toBe([0, 0, 0], "{$call} drawn in colour");
    }
});
