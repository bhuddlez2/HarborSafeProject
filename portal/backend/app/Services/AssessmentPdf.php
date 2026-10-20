<?php

namespace App\Services;

use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use Spatie\Browsershot\Browsershot;

// Turns one assessment, law-enforcement or civilian, into PDF bytes: the same
// record template the law-enforcement View pop-up shows, printed by headless
// Chromium through Browsershot. Shared by AssessmentPdfController and
// CivilianAssessmentPdfController, and swapped for a fake in tests, since a
// real run launches Chromium.
class AssessmentPdf
{
    public function render(LawEnforcementAssessment | PrivateAssessment $record): string
    {
        $html = view('filament.pdf.assessment', [
            'getRecord' => fn () => $record,
        ])->render();

        return Browsershot::html($html)
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
    }
}
