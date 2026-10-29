<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
    /*
    Printed by dompdf (App\Support\HtmlPdf), which is CSS 2.1: no flexbox,
    grid or gap. The partial's markup is the View pop-up's, so the layout is
    redone here with CSS tables, floats and plain blocks. Fonts are dompdf's
    bundled DejaVu, which has every accented letter; nothing is loaded from
    outside this file.
    */
    /* dompdf puts the page margins on the root element, so no "* { margin: 0 }". */
    @page { margin: 15mm; }

    body, div, dl, dt, dd, p { margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: #1b1726;
        background: #fff;
    }

    /* Header */
    .hs-ar-header {
        background-color: #5c0f8b;
        color: #fff;
        border-radius: 9pt;
        padding: 12pt 15pt;
        margin-bottom: 12pt;
    }
    .hs-ar-header-eyebrow {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        color: #cfb7e2;
        margin-bottom: 3pt;
    }
    .hs-ar-header-title {
        font-size: 14pt;
        font-weight: bold;
        margin-bottom: 5pt;
    }
    .hs-ar-header-meta { font-size: 9pt; color: #eadcf5; }
    .hs-ar-header-sep { color: #a77fc6; padding: 0 3pt; }
    .hs-ar-header-id {
        font-family: 'DejaVu Sans Mono', monospace;
        font-size: 7.5pt;
        color: #c3a6da;
        margin-top: 5pt;
    }

    /* Cards */
    .hs-ar-party {
        background-color: #f5f0fb;
        border-radius: 9pt;
        padding: 10pt 12pt;
    }
    .hs-ar-party-label {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        color: #5c0f8b;
        margin-bottom: 8pt;
    }

    /* Victim and offender side by side: a two-cell table, so both cards are
       the same height. */
    /* border-spacing makes the gap between the cards. It also puts 8pt
       outside each, which the negative left margin and the width take back:
       dompdf ignores a table's negative right margin, so the width is the
       A4 text width (595pt less 2 x 15mm = 510pt) plus 2 x 8pt. */
    .hs-ar-parties {
        display: table;
        width: 526pt;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 8pt 0;
        margin: 0 0 12pt -8pt;
        page-break-inside: avoid;
    }
    .hs-ar-parties > .hs-ar-party {
        display: table-cell;
        width: 50%;
        vertical-align: top;
    }

    /* Field grid: two to a row (three on the submitter and submission cards).
       Inline blocks rather than floats, which dompdf mislays inside the table
       cells above. dompdf adds side padding to the width whatever box-sizing
       says, so the gap between columns is on the label and value instead. */
    .hs-ar-field {
        display: inline-block;
        vertical-align: top;
        width: 49%;
        padding-bottom: 7pt;
    }
    .hs-ar-fields--3 .hs-ar-field,
    .hs-ar-submitter .hs-ar-field { width: 32.5%; }
    .hs-ar-field dt {
        font-size: 6.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4pt;
        color: #9068b8;
        margin-bottom: 1.5pt;
        padding-right: 8pt;
    }
    .hs-ar-field dd {
        font-size: 9.5pt;
        font-weight: bold;
        color: #1b1726;
        word-wrap: break-word;
        padding-right: 8pt;
    }

    /* Civilian "Submitted by" card, and the submission records' cards */
    .hs-ar-submitter,
    .hs-ar-card { margin-bottom: 10pt; }

    .hs-ar-pill {
        font-size: 9pt;
        font-weight: bold;
        padding: 1pt 7pt;
        border-radius: 8pt;
    }
    .hs-ar-chips { line-height: 22pt; }
    .hs-ar-chip {
        font-size: 9pt;
        font-weight: bold;
        color: #5c0f8b;
        background-color: #ede3f5;
        padding: 3pt 8pt;
        margin-right: 4pt;
        border-radius: 8pt;
        white-space: nowrap;
    }
    .hs-ar-text {
        font-size: 9.5pt;
        line-height: 1.55;
        color: #1b1726;
        white-space: pre-line;
        word-wrap: break-word;
    }
    .hs-ar-text--empty { color: #6b7280; font-style: italic; }
    .hs-ar-note { font-size: 7.5pt; color: #6b7280; margin-top: 4pt; }

    /* Risk indicators: each question a plain block with room either side,
       the number and YES/NO placed in that room as pills. Absolute, not
       floats or flex: YES/NO comes after the question in the markup, and
       dompdf has no flexbox. */
    .hs-ar-risk {
        border: 1.5pt solid #c9c1d6;
        border-radius: 9pt;
    }
    .hs-ar-risk-header {
        background-color: #ede3f5;
        border-radius: 7.5pt 7.5pt 0 0;
        padding: 8pt 12pt;
    }
    .hs-ar-risk-title {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        color: #5c0f8b;
    }
    .hs-ar-risk-score {
        float: right;
        font-size: 9pt;
        font-weight: bold;
        padding: 1pt 7pt;
        border-radius: 8pt;
    }
    .hs-ar-indicator {
        position: relative;
        padding: 6pt 56pt 6pt 38pt;
        border-bottom: 0.75pt solid #ede3f5;
        page-break-inside: avoid;
    }
    .hs-ar-indicator:last-child { border-bottom: none; border-radius: 0 0 7.5pt 7.5pt; }
    .hs-ar-indicator--yes { background-color: #fff5f5; }
    .hs-ar-indicator-num {
        position: absolute;
        left: 12pt;
        top: 5pt;
        width: 16pt;
        /* Sized by padding: dompdf drops text to the bottom of a tall
           line-height in a positioned box. */
        line-height: 1;
        padding: 4.25pt 0;
        border-radius: 8pt;
        text-align: center;
        font-size: 7.5pt;
        font-weight: bold;
        background-color: #e5e7eb;
        color: #6b7280;
    }
    .hs-ar-indicator-num--yes { background-color: #fee2e2; color: #b91c1c; }
    .hs-ar-indicator-text {
        display: block;
        font-size: 9.5pt;
        line-height: 14pt;
        color: #374151;
    }
    .hs-ar-indicator-badge {
        position: absolute;
        right: 12pt;
        top: 6.5pt;
        padding: 2.75pt 6pt;
        line-height: 1;
        border-radius: 4pt;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.4pt;
        background-color: #f3f4f6;
        color: #6b7280;
    }
    .hs-ar-indicator-badge--yes { background-color: #fee2e2; color: #b91c1c; }

</style>
@isset($followUpLog)
<style>
    /* "Download PDF with log" only (Burchfield B43): a banner over the form,
       and the follow-up Log after it. Each step is a plain block, never a
       table row: dompdf can't split a row across pages, and a note runs to
       2,000 characters, so a long one simply carries on to the next page. */
    .hs-pdf-internal {
        border: 1pt solid #f59e0b;
        background-color: #fffbeb;
        color: #92400e;
        border-radius: 6pt;
        padding: 6pt 10pt;
        margin-bottom: 10pt;
        font-size: 8.5pt;
        font-weight: bold;
    }
    .hs-pdf-log { margin-top: 14pt; }
    .hs-pdf-log-title {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        color: #5c0f8b;
        margin-bottom: 2pt;
    }
    .hs-pdf-log-status { font-size: 8.5pt; color: #6b7280; margin-bottom: 8pt; }
    .hs-pdf-log-step {
        border-left: 2pt solid #c9c1d6;
        padding: 2pt 0 2pt 9pt;
        margin-bottom: 8pt;
    }
    .hs-pdf-log-heading { font-size: 9.5pt; font-weight: bold; color: #1b1726; }
    .hs-pdf-log-meta { font-size: 8pt; color: #6b7280; margin-top: 1pt; }
    .hs-pdf-log-note {
        font-size: 9pt;
        line-height: 1.5;
        color: #1b1726;
        margin-top: 3pt;
        white-space: pre-line;
        word-wrap: break-word;
    }
</style>
@endisset
</head>
<body>
@isset($followUpLog)
    <div class="hs-pdf-internal">Internal: includes follow-up notes</div>
@endisset

{{-- The record's own partial (App\Support\RecordPdf), printed by App\Services\AssessmentPdf. --}}
@include($partial)

@isset($followUpLog)
    <div class="hs-pdf-log">
        <div class="hs-pdf-log-title">Follow-Up Log</div>
        <div class="hs-pdf-log-status">{{ $followUpLog['status'] }}</div>
        @foreach ($followUpLog['steps'] as $step)
            <div class="hs-pdf-log-step">
                <div class="hs-pdf-log-heading">{{ $step['heading'] }}</div>
                <div class="hs-pdf-log-meta">{{ $step['when'] }}@if ($step['who']) · {{ $step['who'] }}@endif</div>
                @if ($step['note'])
                    <p class="hs-pdf-log-note">{{ $step['note'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endisset
</body>
</html>
