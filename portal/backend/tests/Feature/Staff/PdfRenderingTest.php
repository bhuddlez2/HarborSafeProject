<?php

use App\Models\AssessmentAnswers;
use App\Models\County;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\Resource;
use App\Models\ResourceRequestForm;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\SubmitterInfo;
use App\Models\User;
use App\Services\AssessmentPdf;
use App\Services\ServiceFeedbackExport;
use App\Support\HtmlPdf;
use Carbon\Carbon;

/*
PDFs are printed in pure PHP (dompdf, App\Support\HtmlPdf), because the
production host can't run Node or Chromium - so unlike the Browsershot days,
the suite prints them for real. Unsaved records with their relations set by
hand: nothing here touches a database. The other PDF tests still fake
AssessmentPdf where they count calls or make a render fail.

What is pinned: every kind of record prints; nothing is fetched while
printing; a 500-form feedback list stays within shared hosting's memory; and
a comment too long for one printed row is split, never cut short.
*/

// The pages in a PDF's bytes (each page object is "/Type /Page").
function pdfPageCount(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

function pdfAnswers(array $yes): AssessmentAnswers
{
    $answers = new AssessmentAnswers;

    foreach (range(1, 11) as $id) {
        $answers->setAttribute("RiskIndicator{$id}", in_array($id, $yes, true));
    }

    return $answers;
}

function pdfOfficerAssessment(): LawEnforcementAssessment
{
    $record = (new LawEnforcementAssessment)->forceFill([
        'DocumentID' => '0f3c9a2e-1111-4bbb-8ccc-123456789abc',
        'DateCreated' => '2026-10-20 18:45:00',
        'VictimFirstName' => 'Zoë-Amélie', 'VictimLastName' => 'Krzyżanowska',
        'VictimSex' => 'F', 'VictimDOB' => '1990-02-14', 'VictimSafePhoneNumber' => '(555)010-0199',
        'OffenderFirstName' => 'Jürgen', 'OffenderLastName' => 'Ŝmith', 'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'other', 'OffenderVictimRelationshipOther' => 'Former landlord’s brother',
    ]);
    $record->setRelation('assessmentAnswers', pdfAnswers([1, 3, 4]));
    $record->setRelation('submitter', (new User)->forceFill(['name' => 'Officer Fake']));

    return $record;
}

function pdfCivilianAssessment(): PrivateAssessment
{
    $record = (new PrivateAssessment)->forceFill([
        'DocumentID' => '7a7a7a7a-2222-4ddd-8eee-abcdefabcdef',
        'DateCreated' => '2026-10-21 02:10:00',
        'VictimFirstName' => 'Fake', 'VictimLastName' => 'Victim', 'VictimSex' => 'O',
        'VictimSafePhoneNumber' => '(555)010-0142',
    ]);
    $record->setRelation('assessmentAnswers', pdfAnswers([]));
    $record->setRelation('submitterInfo', (new SubmitterInfo)->forceFill([
        'SubmitterFirstName' => 'Helper', 'SubmitterLastName' => 'Fake',
        'SubmitterEmail' => 'helper@example.test', 'SubmitterPhoneNumber' => '(555)010-0123',
    ]));

    return $record;
}

function pdfResourceRequest(): ResourceRequestForm
{
    $record = (new ResourceRequestForm)->forceFill([
        'FormID' => '3c3c3c3c-3333-4fff-8000-000000000003',
        'SubmissionDate' => '2026-10-22 14:00:00',
        'FirstName' => 'Requester', 'LastName' => 'Fake', 'EmailAddress' => 'requester@example.test',
        'SafePhoneNumber' => '(555)010-0177',
        'Message' => str_repeat("Une demande — ça va, merci.\n", 36),
    ]);
    $record->setRelation('resourceTypes', collect([(new Resource)->forceFill(['Name' => 'Emergency shelter'])]));
    $record->setRelation('county', (new County)->forceFill(['Name' => 'Saint Lucie']));

    return $record;
}

function pdfServiceFeedback(?string $comment = 'Très bien.', int $hoursIn = 0): ServiceFeedback
{
    $record = (new ServiceFeedback)->forceFill([
        'FormID' => '4d4d4d4d-4444-4aaa-8bbb-000000000004',
        'SubmissionDate' => Carbon::parse('2026-01-01 15:00')->addHours($hoursIn),
        'Rating' => 4, 'Comment' => $comment, 'Source' => ServiceFeedback::SOURCE_ONLINE,
    ]);
    $record->setRelation('service', (new Service)->forceFill(['Name' => 'Court accompaniment']));

    return $record;
}

test('every kind of record prints as a real PDF', function (Closure $record) {
    $pdf = app(AssessmentPdf::class)->render($record());

    expect($pdf)->toStartWith('%PDF-')
        ->and(pdfPageCount($pdf))->toBeGreaterThanOrEqual(1);
})->with([
    'law-enforcement assessment' => fn () => pdfOfficerAssessment(),
    'civilian assessment' => fn () => pdfCivilianAssessment(),
    'resource request' => fn () => pdfResourceRequest(),
    'service feedback' => fn () => pdfServiceFeedback(),
]);

test('a long message runs onto further pages', function () {
    $record = pdfResourceRequest();
    $record->Message = str_repeat("A long line about what is needed, written out in full.\n", 80);

    expect(pdfPageCount(app(AssessmentPdf::class)->render($record)))->toBeGreaterThan(1);
});

test('nothing is fetched while printing', function () {
    // A throwaway web server that writes down every request it gets and
    // answers 404 at once, so a regression fails here rather than hanging.
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);

    $hits = tempnam(sys_get_temp_dir(), 'pdf-hits');
    $router = tempnam(sys_get_temp_dir(), 'pdf-router');
    file_put_contents($router, '<?php file_put_contents(getenv("PDF_HITS"), $_SERVER["REQUEST_URI"]."\n", FILE_APPEND); http_response_code(404);');

    $server = proc_open([PHP_BINARY, '-S', $address, $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['PDF_HITS' => $hits]);

    try {
        $deadline = microtime(true) + 5;
        while (! @fsockopen(...explode(':', $address)) && microtime(true) < $deadline) {
            usleep(50_000);
        }

        $url = "http://{$address}";
        $pdf = HtmlPdf::render(<<<HTML
            <html><head>
            <link rel="stylesheet" href="{$url}/style.css">
            <style>
                @import url("{$url}/import.css");
                @font-face { font-family: Remote; src: url("{$url}/font.ttf"); }
                body { background-image: url("{$url}/background.png"); font-family: Remote; }
            </style>
            </head><body>
            <img src="{$url}/image.png">
            <img src="file:///etc/hosts">
            <p>Text</p>
            </body></html>
            HTML);

        expect($pdf)->toStartWith('%PDF-')
            ->and(file_get_contents($hits))->toBe('');
    } finally {
        proc_terminate($server);
        proc_close($server);
        @unlink($hits);
        @unlink($router);
    }
});

test('a 500-form feedback list prints within shared hosting memory', function () {
    $feedback = collect(range(1, 500))->map(fn (int $i): ServiceFeedback => pdfServiceFeedback(
        $i % 37 === 0 ? str_repeat('A much longer comment about the service, with détails. ', 18) : "Comment {$i}: helpful.",
        $i * 7,
    ));

    gc_collect_cycles();
    memory_reset_peak_usage();
    $before = memory_get_usage();

    $pdf = app(ServiceFeedbackExport::class)->pdf($feedback);

    // Measured at about 45 MB on top of the booted app; one table of 500 rows
    // took about 125 MB (ServiceFeedbackExport's comment). 128 MB is the usual
    // shared-hosting limit for the whole request.
    expect($pdf)->toStartWith('%PDF-')
        ->and(memory_get_peak_usage() - $before)->toBeLessThan(80 * 1024 * 1024);
});

test('a comment too tall for one printed row is split, none of it lost', function () {
    $export = new class extends ServiceFeedbackExport
    {
        public string $html = '';

        protected function printPdf(string $html): string
        {
            $this->html = $html;

            return parent::printPdf($html);
        }
    };

    // 1000 characters, the most a comment holds - nearly all of it line breaks.
    $comment = 'FIRST '.str_repeat("é\n", 494).'LAST';
    $pdf = $export->pdf(collect([pdfServiceFeedback($comment)]));

    preg_match_all('#<td class="hs-fl-comment"[^>]*>(.*?)</td>#s', $export->html, $cells);

    expect($cells[1])->toHaveCount((int) ceil(495 / ServiceFeedbackExport::PDF_COMMENT_LINES))
        ->and(html_entity_decode(implode("\n", $cells[1])))->toBe($comment)
        ->and(pdfPageCount($pdf))->toBeGreaterThan(1);
});
