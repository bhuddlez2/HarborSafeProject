<?php

namespace App\Services;

use App\Models\ServiceFeedback;
use App\Support\HtmlPdf;
use App\Support\Timezones;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

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

pdf() prints in pure PHP like AssessmentPdf (App\Support\HtmlPdf): US Letter
landscape, "Page n of m" at the foot of every page, the column headings at the
top of every page after the first. ServiceFeedbackExportTest overrides
printPdf() to read the HTML it was given.

Why the PDF is laid out the way it is: dompdf holds the cell map of a table in
memory and redoes it at every page break, so one 500-row table took about
125 MB on its own - more than shared hosting's usual 128 MB limit. The rows
are printed as a stack of small tables instead (ROWS_PER_TABLE), with the same
fixed column widths so they read as one, which halves that. A table's own
<thead> would then repeat at every small table rather than every page, so the
headings on later pages are drawn into the top margin by headings() instead,
from the same widths.

dompdf cannot split a table row across pages either: a row taller than the
page runs off the bottom and the rest of it is lost. So pdfRows() prints a
long comment as several rows, the later ones with the first three cells empty
and no rule between them, each short enough to fit a page.
*/
class ServiceFeedbackExport
{
    public const COLUMNS = [
        'Submitted',
        'Service',
        'Rating (1-5)',
        'Comment',
    ];

    // The PDF's geometry, in points (US Letter landscape is 792 x 612).
    public const PDF_MARGIN = 34;          // 12 mm: sides and above the headings

    public const PDF_MARGIN_BOTTOM = 45;   // 16 mm, the page number sits in it

    public const PDF_HEADING_HEIGHT = 20;  // the column headings row

    // Comment takes whatever the other three leave.
    public const PDF_COLUMN_WIDTHS = [72, 160, 66, null];

    public const ROWS_PER_TABLE = 25;

    // Everything drawn onto the page is black (#44: PDFs are black and white).
    public const PDF_INK = [0, 0, 0];

    // A printed row's comment holds at most this many lines, counted at a
    // deliberately low PDF_CHARS_PER_LINE (the column fits about 90), so a
    // row always fits on a page with room to spare.
    public const PDF_COMMENT_LINES = 20;

    public const PDF_CHARS_PER_LINE = 60;

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
            $writer->getCurrentSheet()->setName('Service Feedback');
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
            'widths' => self::PDF_COLUMN_WIDTHS,
            'tables' => array_chunk($this->pdfRows($feedback), self::ROWS_PER_TABLE),
        ])->render();

        return $this->printPdf($html);
    }

    // rows() for printing: each form's comment in pieces of at most
    // PDF_COMMENT_LINES, one printed row per piece. Each row is the four
    // columns plus whether it continues the form above (its first three
    // cells left empty) and whether the next row continues it.
    private function pdfRows(Collection $feedback): array
    {
        $printed = [];

        foreach ($this->rows($feedback) as [$submitted, $service, $rating, $comment]) {
            $pieces = $this->commentPieces($comment);
            $last = count($pieces) - 1;

            foreach ($pieces as $index => $piece) {
                $printed[] = $index === 0
                    ? [$submitted, $service, $rating, $piece, false, $index < $last]
                    : [null, '', null, $piece, true, $index < $last];
            }
        }

        return $printed;
    }

    /** @return non-empty-list<string> */
    private function commentPieces(string $comment): array
    {
        $pieces = [];
        $piece = [];
        $lines = 0;
        $longest = self::PDF_COMMENT_LINES * self::PDF_CHARS_PER_LINE;

        foreach (preg_split('/\r\n|\r|\n/', $comment) as $line) {
            // A single line longer than a whole piece is cut into pieces, at
            // a space where there is one. Unreachable today - comments are
            // capped at 1000 characters (ServiceFeedbackStoreRequest) - but
            // nothing may be lost if that ever changes.
            foreach ($this->cutLine($line, $longest) as $part) {
                $cost = max(1, (int) ceil(mb_strlen($part) / self::PDF_CHARS_PER_LINE));

                if ($piece !== [] && $lines + $cost > self::PDF_COMMENT_LINES) {
                    $pieces[] = implode("\n", $piece);
                    $piece = [];
                    $lines = 0;
                }

                $piece[] = $part;
                $lines += $cost;
            }
        }

        $pieces[] = implode("\n", $piece);

        return $pieces;
    }

    /** @return non-empty-list<string> */
    private function cutLine(string $line, int $longest): array
    {
        $parts = [];

        while (mb_strlen($line) > $longest) {
            $space = mb_strrpos(mb_substr($line, 0, $longest + 1), ' ');
            $cut = $space === false || $space < $longest / 2 ? $longest : $space;

            $parts[] = mb_substr($line, 0, $cut);
            // The space the line breaks at is the line break itself.
            $line = mb_substr($line, $space === $cut ? $cut + 1 : $cut);
        }

        $parts[] = $line;

        return $parts;
    }

    protected function printPdf(string $html): string
    {
        return HtmlPdf::render($html, 'letter', 'landscape', $this->decoratePage(...));
    }

    // What is drawn straight onto each page: the headings on every page after
    // the first, and the page number. Black only, like the rest of the PDF (#44).
    protected function decoratePage(Canvas $canvas, FontMetrics $fonts, int $page, int $pages): void
    {
        if ($page > 1) {
            $this->headings($canvas, $fonts);
        }

        $this->pageNumber($canvas, $fonts, "Page {$page} of {$pages}");
    }

    // The column headings in the band above the rows, styled as the first
    // page's <th> row (service-feedback-list.blade.php): no fill, black text
    // and a black rule under them.
    private function headings(Canvas $canvas, FontMetrics $fonts): void
    {
        $font = $fonts->getFont(HtmlPdf::FONT, 'bold');
        $size = 7.5;
        $top = self::PDF_MARGIN;
        $x = self::PDF_MARGIN;
        $width = $canvas->get_width() - 2 * self::PDF_MARGIN;
        $last = count(self::COLUMNS) - 1;

        $canvas->line($x, $top + self::PDF_HEADING_HEIGHT, $x + $width, $top + self::PDF_HEADING_HEIGHT, self::PDF_INK, 1.5);

        foreach (self::COLUMNS as $index => $column) {
            $columnWidth = self::PDF_COLUMN_WIDTHS[$index] ?? $width - array_sum(array_slice(self::PDF_COLUMN_WIDTHS, 0, $last));
            $label = mb_strtoupper($column);
            $textX = $index === 2
                ? $x + ($columnWidth - $fonts->getTextWidth($label, $font, $size)) / 2
                : $x + 6;

            $canvas->text($textX, $top + 6, $label, $font, $size, self::PDF_INK);
            $x += $columnWidth;
        }
    }

    private function pageNumber(Canvas $canvas, FontMetrics $fonts, string $text): void
    {
        $font = $fonts->getFont(HtmlPdf::FONT);
        $size = 7;

        $canvas->text(
            ($canvas->get_width() - $fonts->getTextWidth($text, $font, $size)) / 2,
            $canvas->get_height() - self::PDF_MARGIN_BOTTOM / 2 - $size / 2,
            $text,
            $font,
            $size,
            self::PDF_INK,
        );
    }
}
