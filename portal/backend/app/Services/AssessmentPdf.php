<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Support\HtmlPdf;
use App\Support\RecordPdf;
use Closure;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

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
    public const MARGIN = 42.5;         // 15 mm, the page's side margins

    public const FOOTER_FROM_BOTTOM = 28;

    public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
    {
        return HtmlPdf::render(RecordPdf::html($record), 'letter', 'portrait', $this->footer((string) $record->getKey()));
    }

    // The follow-up page's "Download PDF with Log" (Burchfield B43): the
    // same form plus its follow-up Log - see RecordPdf::htmlWithLog().
    public function renderWithLog(FollowUp $followUp): string
    {
        $html = RecordPdf::htmlWithLog($followUp);

        return HtmlPdf::render($html, 'letter', 'portrait', $this->footer((string) $followUp->form()?->getKey()));
    }

    // Under every page, black like the rest (#44): the record's ID at the
    // left and "Page 1 of 2" at the right, so a page that comes loose from a
    // printout can be matched to its record.
    public function footer(string $recordId): Closure
    {
        return function (Canvas $canvas, FontMetrics $fonts, int $page, int $pages) use ($recordId): void {
            $font = $fonts->getFont(HtmlPdf::FONT);
            $size = 7.5;
            $y = $canvas->get_height() - self::FOOTER_FROM_BOTTOM;
            $pageText = "Page {$page} of {$pages}";

            $canvas->text(self::MARGIN, $y, "Record ID: {$recordId}", $font, $size, [0, 0, 0]);
            $canvas->text($canvas->get_width() - self::MARGIN - $fonts->getTextWidth($pageText, $font, $size), $y, $pageText, $font, $size, [0, 0, 0]);
        };
    }
}
