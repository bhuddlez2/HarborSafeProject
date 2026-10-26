<?php

namespace App\Support;

use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use InvalidArgumentException;

/*
Which Blade partial shows each kind of record - the same one in its View
pop-up and in its PDF - and the printable page for it: the partial wrapped in
filament/pdf/record.blade.php, which carries the partial's styles inline,
since the PDF renderer (dompdf, App\Support\HtmlPdf) never sees the panel's
theme. dompdf has no flexbox or grid, so that page re-lays the partial's
markup with CSS 2.1; a partial's markup change needs a look at its PDF too.

Printing is App\Services\AssessmentPdf's job (swappable in tests). Neither does
any access checking or logging: each controller fetches the record through
its own scope first, and records the download where the record shows a safe
phone number (AssessmentPdfController, CivilianAssessmentPdfController,
ResourceRequestPdfController, SubmissionPdfController).

isPdf is true on every printable page: a partial leaves out anything that
must not travel in an export - for service feedback, who entered it and the
scan (AS, 2026-10-20: no log information of any kind on an export).
*/
final class RecordPdf
{
    public const ASSESSMENT = 'filament.infolists.components.assessment-record';

    public const SERVICE_FEEDBACK = 'filament.infolists.components.service-feedback-record';

    public const RESOURCE_REQUEST = 'filament.infolists.components.resource-request-record';

    public static function html(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
    {
        [$partial, $title] = match (true) {
            $record instanceof LawEnforcementAssessment,
            $record instanceof PrivateAssessment => [self::ASSESSMENT, 'LAP Assessment'],
            $record instanceof ResourceRequestForm => [self::RESOURCE_REQUEST, 'Resource Request'],
            $record instanceof ServiceFeedback => [self::SERVICE_FEEDBACK, 'Service Feedback'],
            default => throw new InvalidArgumentException('No printable record for '.$record::class),
        };

        return view('filament.pdf.record', [
            'partial' => $partial,
            'title' => $title,
            'getRecord' => fn () => $record,
            'isPdf' => true,
        ])->render();
    }
}
