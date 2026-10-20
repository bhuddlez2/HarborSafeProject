<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

/*
Renders one record as a downloadable PDF: the same Blade partial its View
pop-up shows, wrapped in filament/pdf/record.blade.php (which carries the
partial's styles inline, since Chromium never sees the panel's theme).

It does no access checking. Each controller must fetch the record it is given
through its own check or scope first (AssessmentPdfController,
CivilianAssessmentPdfController, SubmissionPdfController).
*/
final class RecordPdf
{
    public const ASSESSMENT = 'filament.infolists.components.assessment-record';

    public const SERVICE_FEEDBACK = 'filament.infolists.components.service-feedback-record';

    public const RESOURCE_REQUEST = 'filament.infolists.components.resource-request-record';

    public static function download(string $partial, Model $record, string $title, string $filenamePrefix): Response
    {
        $html = view('filament.pdf.record', [
            'partial' => $partial,
            'title' => $title,
            'getRecord' => fn () => $record,
            'isPdf' => true,
        ])->render();

        // All HTML is from our own templates with Blade escaping, which is
        // what makes noSandbox() acceptable here.
        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->noSandbox()
            ->showBackground()
            ->timeout(30)
            ->addChromiumArguments([
                '--disable-background-networking',
                '--disable-extensions',
                '--disable-sync',
            ])
            ->pdf();

        $filename = $filenamePrefix.'-'.substr((string) $record->getKey(), 0, 8).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
