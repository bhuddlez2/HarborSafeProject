<?php

namespace App\Support;

use App\Filament\FollowUps\FollowUpLog;
use App\Models\FollowUp;
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

The one exception (Burchfield B43, 2026-10-28): htmlWithLog(), the follow-up
page's "Download PDF with log", which Harbor Safe may have to provide. The
same printed form, under an "Internal: includes follow-up notes" banner and
followed by the follow-up Log. Offered only on the follow-up page, to those
who work that form; the plain html() never carries any of it.
*/
final class RecordPdf
{
    public const ASSESSMENT = 'filament.infolists.components.assessment-record';

    public const SERVICE_FEEDBACK = 'filament.infolists.components.service-feedback-record';

    public const RESOURCE_REQUEST = 'filament.infolists.components.resource-request-record';

    public static function htmlWithLog(FollowUp $followUp): string
    {
        $form = $followUp->form() ?? throw new InvalidArgumentException('The follow-up has no form.');

        return self::page($form, [
            'status' => $followUp->Kind->label().' · Current Status: '.$followUp->Status->label(),
            'steps' => FollowUpLog::steps($followUp),
        ]);
    }

    public static function html(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record): string
    {
        return self::page($record);
    }

    private static function page(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | ServiceFeedback $record, ?array $followUpLog = null): string
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
            ...($followUpLog === null ? [] : ['followUpLog' => $followUpLog]),
        ])->render();
    }
}
