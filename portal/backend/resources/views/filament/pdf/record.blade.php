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

    Black and white only (#44): a white page, black text, black lines, no
    fills. Where the screen uses colour to say something, the PDF says it in
    words or weight instead: a yes answer is bold, high risk is written out
    (assessment-record.blade.php, isPdf). The screen keeps its colours, which
    come from theme.css, not from here.
    */
    /*
    Laid out as a printed document, not a copy of the pop-up's cards: no
    boxes or rounded corners, just headings, labels and rules. Two line
    weights and no others - 1pt for a section's rule and the header's,
    0.5pt between rows - so every line on the page matches its kind.
    */
    /* US Letter (AssessmentPdf). dompdf puts the page margins on the root
       element, so no "* { margin: 0 }". The bottom margin leaves room for
       the footer AssessmentPdf draws on every page. */
    @page { margin: 15mm 15mm 20mm; }

    body, div, dl, dt, dd, p { margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        line-height: 1.35;
        color: #000;
        background: #fff;
    }

    a { color: #000; text-decoration: none; }

    /* Header: the organisation, the form's name, when and by whom, and the
       record's ID, over a 1pt rule. */
    .hs-ar-header {
        border-bottom: 1pt solid #000;
        padding-bottom: 8pt;
        margin-bottom: 16pt;
    }
    .hs-ar-header-eyebrow {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
    }
    .hs-ar-header-title {
        font-size: 17pt;
        font-weight: bold;
        margin: 2pt 0 4pt;
    }
    .hs-ar-header-meta { font-size: 9.5pt; }
    .hs-ar-header-sep { padding: 0 3pt; }
    .hs-ar-header-id {
        font-family: 'DejaVu Sans Mono', monospace;
        font-size: 8pt;
        margin-top: 3pt;
    }

    /* Sections (Victim, Offender, Submitted by, ...): a small heading over a
       1pt rule, then the fields, with no box around them. */
    .hs-ar-party-label {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        border-bottom: 1pt solid #000;
        padding-bottom: 3pt;
        margin-bottom: 7pt;
    }

    .hs-ar-submitter,
    .hs-ar-card { margin-bottom: 16pt; }

    /* Victim and offender side by side: a two-cell table, so the two
       sections line up. border-spacing makes the gap between them; it also
       puts 14pt outside each, which the negative left margin and the width
       take back: dompdf ignores a table's negative right margin, so the
       width is the Letter text width (612pt less 2 x 15mm = 527pt) plus
       2 x 14pt. */
    .hs-ar-parties {
        display: table;
        width: 555pt;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 14pt 0;
        margin: 0 0 16pt -14pt;
        page-break-inside: avoid;
    }
    .hs-ar-parties > .hs-ar-party {
        display: table-cell;
        width: 50%;
        vertical-align: top;
    }

    /* Fields: a small label over its value, two to a row (three on the
       submitter and submission sections, the middle one - an email - widest
       so an address isn't split). Inline blocks rather than floats, which
       dompdf mislays inside the table cells above. dompdf adds side padding
       to the width whatever box-sizing says, so the gap between columns is
       on the label and value instead. */
    .hs-ar-field {
        display: inline-block;
        vertical-align: top;
        width: 49%;
        padding-bottom: 8pt;
    }
    .hs-ar-fields--3 .hs-ar-field,
    .hs-ar-submitter .hs-ar-field { width: 29%; }
    .hs-ar-fields--3 .hs-ar-field:nth-child(2),
    .hs-ar-submitter .hs-ar-field:nth-child(2) { width: 39%; }
    .hs-ar-field dt {
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4pt;
        margin-bottom: 1.5pt;
        padding-right: 10pt;
    }
    .hs-ar-field dd {
        font-size: 10pt;
        word-wrap: break-word;
        padding-right: 10pt;
    }

    /* A rating ("3 out of 5") and the resources asked about: plain text,
       the resources separated by 0.5pt boxes so each reads on its own. */
    .hs-ar-pill { font-weight: bold; }
    .hs-ar-chips { line-height: 20pt; }
    .hs-ar-chip {
        font-size: 9.5pt;
        border: 0.5pt solid #000;
        padding: 2pt 6pt;
        margin-right: 4pt;
        white-space: nowrap;
    }
    .hs-ar-text {
        font-size: 10pt;
        line-height: 1.5;
        white-space: pre-line;
        word-wrap: break-word;
    }
    .hs-ar-text--empty { font-style: italic; }
    .hs-ar-note { font-size: 8pt; font-style: italic; margin-top: 2pt; }

    /* Risk indicators: the heading and score over a 1pt rule, then one row
       per question with a 0.5pt rule under it - number, question, answer.
       The number and answer are placed in the row's side padding with
       absolute positions: YES/NO comes after the question in the markup, and
       dompdf has no flexbox. A yes is bold - number, question and answer -
       and a no plain: the page is black and white (#44). */
    .hs-ar-risk-header {
        border-bottom: 1pt solid #000;
        padding-bottom: 3pt;
    }
    .hs-ar-risk-title {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
    }
    .hs-ar-risk-score {
        float: right;
        font-size: 9pt;
        font-weight: bold;
    }
    .hs-ar-indicator {
        position: relative;
        padding: 5pt 40pt 5pt 22pt;
        border-bottom: 0.5pt solid #000;
        page-break-inside: avoid;
    }
    .hs-ar-indicator-num {
        position: absolute;
        left: 0;
        top: 5pt;
        width: 14pt;
        text-align: right;
        font-size: 10pt;
        line-height: 14pt;
    }
    .hs-ar-indicator-text {
        display: block;
        font-size: 10pt;
        line-height: 14pt;
    }
    .hs-ar-indicator-badge {
        position: absolute;
        right: 0;
        top: 5pt;
        width: 30pt;
        text-align: right;
        font-size: 9pt;
        line-height: 14pt;
        letter-spacing: 0.4pt;
    }
    .hs-ar-indicator--yes .hs-ar-indicator-text,
    .hs-ar-indicator-num--yes,
    .hs-ar-indicator-badge--yes { font-weight: bold; }

</style>
@isset($followUpLog)
<style>
    /* "Download PDF with Log" only (Burchfield B43): a banner over the form,
       and the follow-up Log after it. Each step is a plain block, never a
       table row: dompdf can't split a row across pages, and a note runs to
       2,000 characters, so a long one simply carries on to the next page. */
    /* The same two weights as the form: the banner and the Log's heading
       1pt, each step a 0.5pt rule under it. */
    .hs-pdf-internal {
        border: 1pt solid #000;
        padding: 5pt 8pt;
        margin-bottom: 14pt;
        font-size: 9pt;
        font-weight: bold;
    }
    .hs-pdf-log { margin-top: 20pt; }
    .hs-pdf-log-title {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        border-bottom: 1pt solid #000;
        padding-bottom: 3pt;
    }
    .hs-pdf-log-status { font-size: 9pt; margin: 4pt 0 2pt; }
    /* A step moves whole to the next page rather than leaving its heading
       behind; one taller than a page still splits, so nothing is lost. */
    .hs-pdf-log-step {
        border-bottom: 0.5pt solid #000;
        padding: 6pt 0;
        page-break-inside: avoid;
    }
    .hs-pdf-log-heading { font-size: 10pt; font-weight: bold; }
    .hs-pdf-log-meta { font-size: 8.5pt; margin-top: 1pt; }
    .hs-pdf-log-note {
        font-size: 10pt;
        line-height: 1.5;
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
