@php
    use App\Services\ServiceFeedbackExport as Export;

    // Printed by dompdf (App\Support\HtmlPdf): CSS 2.1, DejaVu Sans, nothing
    // loaded from outside this file. Every table shares these fixed widths,
    // and ServiceFeedbackExport::headings() draws the same columns at the top
    // of each later page, so the stack of small tables reads as one.
    $commentWidth = 792 - 2 * Export::PDF_MARGIN - array_sum(array_filter($widths));
    // A cell's CSS width leaves out its 6pt padding either side.
    $cell = collect($widths)->map(fn (?int $width): int|float => ($width ?? $commentWidth) - 12)->all();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Service Feedback</title>
<style>
    /* Black and white only (#44): white page, black text and rules, no
       fills - as ServiceFeedbackExport draws its headings on later pages. */
    /* The top margin leaves room for the headings drawn on later pages. */
    @page {
        margin: {{ Export::PDF_MARGIN + Export::PDF_HEADING_HEIGHT }}pt {{ Export::PDF_MARGIN }}pt {{ Export::PDF_MARGIN_BOTTOM }}pt {{ Export::PDF_MARGIN }}pt;
    }

    /* dompdf puts the page margins on the root element, so no "* { margin: 0 }". */
    body, div { margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8pt;
        color: #000;
        background: #fff;
    }

    .hs-fl-header {
        border: 1.5pt solid #000;
        border-radius: 7.5pt;
        padding: 10pt 12pt;
        margin-bottom: 10pt;
    }
    .hs-fl-eyebrow {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        margin-bottom: 3pt;
    }
    .hs-fl-title {
        font-size: 14pt;
        font-weight: bold;
    }

    /* Not table-layout: fixed - dompdf then splits the columns equally and
       ignores the widths. */
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        height: {{ Export::PDF_HEADING_HEIGHT - 10 }}pt;
        font-size: 7.5pt;
        font-weight: bold;
        text-align: left;
        text-transform: uppercase;
        padding: 5pt 6pt;
        border-bottom: 1.5pt solid #000;
    }
    td {
        padding: 5pt 6pt;
        border-bottom: 0.5pt solid #000;
        vertical-align: top;
        word-wrap: break-word;
    }
    td.hs-fl-nowrap { white-space: nowrap; }
    td.hs-fl-centre, th.hs-fl-centre { text-align: center; }
    td.hs-fl-comment { white-space: pre-wrap; }
    /* One form's long comment printed over several rows (pdfRows()). */
    tr.hs-fl-continues td { border-bottom: none; padding-bottom: 0; }
    tr.hs-fl-continued td { padding-top: 0; }
</style>
</head>
<body>
    <div class="hs-fl-header">
        <div class="hs-fl-eyebrow">Harbor Safe</div>
        <div class="hs-fl-title">Service Feedback</div>
    </div>

    {{-- Only what each form holds: no filters, export date or names (ServiceFeedbackExport).
         dompdf ignores <col>, so each table's first row carries the widths. --}}
    <table>
        <tr>
            @foreach ($columns as $index => $column)
                <th @class(['hs-fl-centre' => $index === 2]) style="width: {{ $cell[$index] }}pt">{{ $column }}</th>
            @endforeach
        </tr>
    </table>

    {{-- ServiceFeedbackExport::ROWS_PER_TABLE rows a table: see its comment on memory. --}}
    @foreach ($tables as $rows)
        <table>
            @foreach ($rows as [$submitted, $service, $rating, $comment, $continued, $continues])
                <tr @class(['hs-fl-continued' => $continued, 'hs-fl-continues' => $continues])>
                    <td class="hs-fl-nowrap" @if ($loop->first) style="width: {{ $cell[0] }}pt" @endif>{{ $submitted?->format('Y-m-d') }}</td>
                    <td @if ($loop->first) style="width: {{ $cell[1] }}pt" @endif>{{ $service }}</td>
                    <td class="hs-fl-centre" @if ($loop->first) style="width: {{ $cell[2] }}pt" @endif>{{ $rating }}</td>
                    <td class="hs-fl-comment" @if ($loop->first) style="width: {{ $cell[3] }}pt" @endif>{{ $comment }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
