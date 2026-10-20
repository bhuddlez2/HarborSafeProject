<?php

namespace App\Services;

use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Support\RecordPdf;
use Spatie\Browsershot\Browsershot;

// Prints one record as PDF bytes through headless Chromium (Browsershot): an
// officer or civilian assessment, a resource request or a service feedback
// form, each in the same designed partial its View pop-up shows
// (App\Support\RecordPdf). The plain form only - never follow-up, log or
// staff-entry information. Shared by every PDF controller, and swapped for a
// fake in tests, since a real run launches Chromium.
class AssessmentPdf
{
    public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
    {
        // All HTML is from our own templates with Blade escaping, which is
        // what makes noSandbox() acceptable here.
        return Browsershot::html(RecordPdf::html($record))
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
