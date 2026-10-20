<?php

namespace App\Services;

use App\Models\ServiceFeedback;
use App\Support\Timezones;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Browsershot\Browsershot;

/*
The service feedback list as a file, for Harbor Safe to hand to grant-giving
organisations (team plan task 34): the forms the Service feedback table is
filtered down to, one form per row, as Excel or PDF. ListServiceFeedback's
Export button passes in the table's own filtered query, so a file never holds
more or less than the list on screen.

Only what the form itself holds, nothing about how it reached Harbor Safe
(AS, 2026-10-20): the day it was submitted (Eastern), the service, the rating
and the comment. No source, no staff names, no scan, no filters, no export
date - nothing that reads as a log. Harbor Safe knows comments are included;
the Excel file lets them drop that column before sending. Deliberately NOT
logged either, unlike assessment PDFs (assessment_access_log): feedback is
data collection.

pdf() prints through headless Chromium like AssessmentPdf; tests override
printPdf() rather than launch it.
*/
class ServiceFeedbackExport
{
    public const COLUMNS = [
        'Submitted',
        'Service',
        'Rating (1-5)',
        'Comment',
    ];

    // One array per form, in COLUMNS order. Submitted is the Eastern day,
    // left as a Carbon for each format to write as it should (a real date
    // cell in Excel, so it sorts and filters).
    public function rows(Collection $feedback): array
    {
        return $feedback->map(fn (ServiceFeedback $form): array => [
            $form->SubmissionDate?->copy()->setTimezone(Timezones::DISPLAY)->startOfDay(),
            $form->service?->Name ?? '',
            $form->Rating,
            $form->Comment ?? '',
        ])->all();
    }

    // The .xlsx file's bytes: a header row, then one row per form.
    public function xlsx(Collection $feedback): string
    {
        $path = tempnam(sys_get_temp_dir(), 'feedback-export');

        $options = new Options;
        $options->setColumnWidth(12, 1);
        $options->setColumnWidth(28, 2);
        $options->setColumnWidth(12, 3);
        $options->setColumnWidth(70, 4);

        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Service feedback');
            $writer->addRow(Row::fromValues(self::COLUMNS, (new Style)->setFontBold()));

            $dateStyle = (new Style)->setFormat('yyyy-mm-dd');
            $wrapStyle = (new Style)->setShouldWrapText();

            foreach ($this->rows($feedback) as [$submitted, $service, $rating, $comment]) {
                $writer->addRow(new Row([
                    // The Eastern day; Excel dates carry no zone.
                    Cell::fromValue($submitted?->toDateTimeImmutable(), $dateStyle),
                    Cell::fromValue($service),
                    Cell::fromValue($rating),
                    Cell::fromValue($comment, $wrapStyle),
                ]));
            }

            $writer->close();

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    // The PDF's bytes: the same rows as a printed table under a plain title.
    public function pdf(Collection $feedback): string
    {
        $html = view('filament.pdf.service-feedback-list', [
            'columns' => self::COLUMNS,
            'rows' => $this->rows($feedback),
        ])->render();

        return $this->printPdf($html);
    }

    protected function printPdf(string $html): string
    {
        return Browsershot::html($html)
            ->format('Letter')
            ->landscape()
            ->margins(12, 12, 16, 12)
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<div></div>')
            ->footerHtml('<div style="font-size:9px;width:100%;text-align:center;color:#6b7280;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>')
            ->noSandbox()
            ->showBackground()
            ->timeout(60)
            ->addChromiumArguments([
                '--disable-background-networking',
                '--disable-extensions',
                '--disable-sync',
            ])
            ->pdf();
    }
}
