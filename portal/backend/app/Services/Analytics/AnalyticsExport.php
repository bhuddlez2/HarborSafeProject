<?php

namespace App\Services\Analytics;

use App\Support\HtmlPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/*
One Analytics tab as a file, Excel or PDF (Burchfield B68, AS 2026-10-28), from
its Export button (App\Filament\Analytics\AnalyticsPage). It is written from
the same sections() the page draws - tiles, then chart cards - so the file
holds exactly what the tab shows for the applied date range and filter, and
nothing else: totals only, no names, phone numbers, comments or typed
"Other" text (the stats services never select them).

At the top: the tab's name, then $lines - the date range, the filter on the
assessment tabs, and the export date. Unlike the service feedback export,
which leaves those out on purpose, a total means nothing without its range.

Not logged, like the service feedback export: there is no one's record in it.

Excel: one sheet; counts are number cells. Where the page shows a number
differently ("5 · 40%", "4.2 / 5") that text goes in a third column.
PDF: US Letter portrait, each chart as a table with a plain bar beside each
number (dompdf is CSS 2.1; see App\Support\HtmlPdf), "Page n of m" at the foot.
AnalyticsExportTest overrides printPdf() to read the HTML.
*/
class AnalyticsExport
{
    public const PDF_MARGIN = 34;          // 12 mm: sides and top

    public const PDF_MARGIN_BOTTOM = 45;   // 16 mm, the page number sits in it

    /**
     * @param  list<string>  $lines
     * @param  list<array>  $sections  AnalyticsPage::sections()
     */
    public function xlsx(string $title, array $lines, array $sections): string
    {
        $path = tempnam(sys_get_temp_dir(), 'analytics-export');

        $options = new Options;
        $options->setColumnWidth(46, 1);
        $options->setColumnWidth(16, 2);
        $options->setColumnWidth(40, 3);

        $writer = new Writer($options);
        $bold = (new Style)->setFontBold();
        $heading = (new Style)->setFontBold()->setFontSize(13);

        try {
            $writer->openToFile($path);
            // Excel allows 31 characters and no : \ / ? * [ ] in a sheet name.
            $writer->getCurrentSheet()->setName(mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '', $title), 0, 31));

            $writer->addRow(Row::fromValues([$title], (new Style)->setFontBold()->setFontSize(14)));

            foreach ($lines as $line) {
                $writer->addRow(Row::fromValues([$line]));
            }

            foreach ($sections as $section) {
                $writer->addRow(Row::fromValues([]));

                if (filled($section['heading'] ?? null)) {
                    $writer->addRow(Row::fromValues([$section['heading']], $heading));
                }

                foreach ($section['tiles'] as $tile) {
                    $writer->addRow(new Row([
                        Cell::fromValue($tile['label'], $bold),
                        Cell::fromValue(self::tileValue($tile['value'])),
                        Cell::fromValue($tile['sub'] ?? ''),
                    ]));
                }

                foreach ($section['cards'] as $card) {
                    $writer->addRow(Row::fromValues([]));
                    $writer->addRow(Row::fromValues([$card['title']], $heading));

                    if (filled($card['note'])) {
                        $writer->addRow(Row::fromValues([$card['note']]));
                    }

                    if ($card['type'] === 'table') {
                        $writer->addRow(Row::fromValues([$card['firstColumn'], ...$card['columns']], $bold));

                        foreach ($card['rows'] as $row) {
                            $writer->addRow(Row::fromValues([$row['label'], ...$row['values']]));
                        }
                    } else {
                        $writer->addRow(Row::fromValues([
                            '',
                            ucfirst($card['noun']),
                            self::hasShownAs($card) ? 'As shown' : '',
                        ], $bold));

                        foreach ($card['bars'] as $bar) {
                            $writer->addRow(Row::fromValues([
                                $bar['name'],
                                self::barValue($bar),
                                self::shownAs($bar) ?? '',
                            ]));
                        }
                    }

                    if (filled($card['footnote'])) {
                        $writer->addRow(Row::fromValues([$card['footnote']]));
                    }
                }
            }

            $writer->close();

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array>  $sections  AnalyticsPage::sections()
     */
    public function pdf(string $title, array $lines, array $sections): string
    {
        return $this->printPdf(view('filament.pdf.analytics', [
            'title' => $title,
            'lines' => $lines,
            'sections' => $sections,
        ])->render());
    }

    // A tile's text as a number when it is just one ("1,234", "2.3"), so it
    // can be added up; anything else ("2 (67%)", "4.2 / 5", "—") as written.
    public static function tileValue(string $value): int | float | string
    {
        if (! preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$|^\d+(\.\d+)?$/', $value)) {
            return $value;
        }

        $number = str_replace(',', '', $value);

        return str_contains($number, '.') ? (float) $number : (int) $number;
    }

    // A bar's number, or an empty cell where the page shows none ("—", an
    // average with no ratings, which the bar draws as 0).
    public static function barValue(array $bar): int | float | string
    {
        if ($bar['display'] === '—') {
            return '';
        }

        $value = $bar['value'];

        return floor($value) === $value ? (int) $value : $value;
    }

    // The page's own text for a bar, when it says more than the plain number.
    public static function shownAs(array $bar): ?string
    {
        return $bar['display'] === number_format($bar['value']) ? null : $bar['display'];
    }

    private static function hasShownAs(array $card): bool
    {
        foreach ($card['bars'] as $bar) {
            if (self::shownAs($bar) !== null) {
                return true;
            }
        }

        return false;
    }

    protected function printPdf(string $html): string
    {
        return HtmlPdf::render($html, 'letter', 'portrait', $this->decoratePage(...));
    }

    // The page number, drawn straight onto each page: black, like the rest
    // of the PDF (#44: PDFs are black and white).
    protected function decoratePage(Canvas $canvas, FontMetrics $fonts, int $page, int $pages): void
    {
        $text = "Page {$page} of {$pages}";
        $font = $fonts->getFont(HtmlPdf::FONT);
        $size = 7;

        $canvas->text(
            ($canvas->get_width() - $fonts->getTextWidth($text, $font, $size)) / 2,
            $canvas->get_height() - self::PDF_MARGIN_BOTTOM / 2 - $size / 2,
            $text,
            $font,
            $size,
            [0, 0, 0],
        );
    }
}
