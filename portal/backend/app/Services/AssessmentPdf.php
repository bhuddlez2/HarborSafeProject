<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Support\HtmlPdf;
use App\Support\RecordPdf;

// Prints one record as PDF bytes in pure PHP (App\Support\HtmlPdf): an
// officer or civilian assessment, a resource request or a service feedback
// form, each in the same designed partial its View pop-up shows
// (App\Support\RecordPdf). The plain form only - never follow-up, log or
// staff-entry information - except renderWithLog(), the follow-up page's
// separate internal export. Shared by every PDF controller; most tests swap it
// for a fake so they can count calls or make it fail (PdfRenderingTest prints
// for real).
class AssessmentPdf
{
    public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
    {
        return HtmlPdf::render(RecordPdf::html($record), 'A4');
    }

    // The follow-up page's "Download PDF with log" (Burchfield B43): the
    // same form plus its follow-up Log - see RecordPdf::htmlWithLog().
    public function renderWithLog(FollowUp $followUp): string
    {
        return HtmlPdf::render(RecordPdf::htmlWithLog($followUp), 'A4');
    }
}
