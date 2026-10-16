<?php

namespace App\Support;

use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

/*
Renders an assessment record as a downloadable PDF - the same Blade partial
the View pop-up shows, wrapped in filament/pdf/assessment.blade.php.

It does no access checking. Each controller must fetch the record it is given
through its own scope first (AssessmentPdfController, CivilianAssessmentPdfController).
*/
final class AssessmentPdf
{
    public static function download(LawEnforcementAssessment | PrivateAssessment $record, string $filenamePrefix): Response
    {
        $html = view('filament.pdf.assessment', [
            'getRecord' => fn () => $record,
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

        $filename = $filenamePrefix.'-'.substr($record->DocumentID, 0, 8).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
