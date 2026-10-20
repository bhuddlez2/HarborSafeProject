<?php

namespace App\Services;

use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use Spatie\Browsershot\Browsershot;

// Turns one form into PDF bytes, printed by headless Chromium through
// Browsershot: an assessment (law-enforcement or civilian) in the record
// template the View pop-up shows, or a resource request in its follow-up
// page's layout. The plain form only - never follow-up, log or staff
// information. Shared by the three PDF controllers, and swapped for a fake in
// tests, since a real run launches Chromium.
class AssessmentPdf
{
    public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm $record): string
    {
        $html = view('filament.pdf.assessment', $record instanceof ResourceRequestForm
            ? ['title' => 'Resource request', 'recordView' => 'filament.follow-ups.resource-request', 'request' => $record]
            : ['getRecord' => fn () => $record],
        )->render();

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
