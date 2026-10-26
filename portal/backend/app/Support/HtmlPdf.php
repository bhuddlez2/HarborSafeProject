<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

/*
Turns our own HTML into PDF bytes in pure PHP (dompdf), for App\Services\
AssessmentPdf and App\Services\ServiceFeedbackExport. Pure PHP because the
production host is shared hosting with no Node, no Chromium and no way to run
binaries - which is why Browsershot went.

Nothing is fetched while rendering: remote loading, PHP and JavaScript are
off, and every protocol dompdf knows (http, https, file, data, phar) refuses
every address, so a stray <img>, <link> or url() in a template renders as
nothing rather than making a request or reading a file. (Refusing rules
rather than an empty protocol list: dompdf trips over a protocol that isn't
listed at all.) Every style is inline in the template, and the only
fonts are the ones dompdf bundles - DejaVu Sans, which covers accented and
non-Latin letters (the default Helvetica does not). dompdf's own work files
(font metrics, subsets) go in storage/framework/dompdf, never vendor/.

dompdf is CSS 2.1: no flexbox or grid. The PDF views (resources/views/
filament/pdf/) lay things out with tables, floats and blocks for that reason.

$eachPage, when given, draws on every finished page - page numbers, repeated
headings - as fn (Canvas, FontMetrics, int $page, int $pageCount).
*/
final class HtmlPdf
{
    public const FONT = 'DejaVu Sans';

    public static function render(string $html, string $paper = 'A4', string $orientation = 'portrait', ?callable $eachPage = null): string
    {
        $work = storage_path('framework/dompdf');

        if (! is_dir($work)) {
            mkdir($work, 0775, true);
        }

        $options = (new Options)
            ->setIsRemoteEnabled(false)
            ->setIsPhpEnabled(false)
            ->setIsJavascriptEnabled(false)
            ->setAllowedProtocols(array_fill_keys(
                ['data://', 'file://', 'http://', 'https://', 'phar://'],
                ['rules' => [fn (): array => [false, 'Nothing is loaded while printing a PDF.']]],
            ))
            ->setChroot([$work])
            ->setTempDir($work)
            ->setFontCache($work)
            ->setDefaultFont(self::FONT)
            ->setDefaultMediaType('print')
            ->setIsFontSubsettingEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        if ($eachPage) {
            $dompdf->getCanvas()->page_script(
                fn (int $page, int $pageCount, Canvas $canvas, FontMetrics $fonts) => $eachPage($canvas, $fonts, $page, $pageCount)
            );
        }

        return $dompdf->output();
    }
}
