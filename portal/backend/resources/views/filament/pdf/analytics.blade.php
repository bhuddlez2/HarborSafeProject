@php
    use App\Services\Analytics\AnalyticsExport as Export;

    // Printed by dompdf (App\Support\HtmlPdf): CSS 2.1, DejaVu Sans, nothing
    // loaded from outside this file. One Analytics tab, from the same
    // sections() the page draws. Letter portrait: 612pt less the side margins.
    $width = 612 - 2 * Export::PDF_MARGIN;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    /* Black and white only (#44): white page, black text, rules and bars.
       Nothing depends on colour: each card is one series, every bar has its
       name on the left and its number printed on the right, and the tables
       are plain numbers. */
    @page {
        margin: {{ Export::PDF_MARGIN }}pt {{ Export::PDF_MARGIN }}pt {{ Export::PDF_MARGIN_BOTTOM }}pt {{ Export::PDF_MARGIN }}pt;
    }

    /* dompdf puts the page margins on the root element, so no "* { margin: 0 }". */
    body, div, p, h2, h3 { margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8.5pt;
        color: #000;
        background: #fff;
    }

    .hs-an-header {
        border: 1.5pt solid #000;
        border-radius: 7.5pt;
        padding: 10pt 12pt;
        margin-bottom: 12pt;
    }
    .hs-an-eyebrow {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        margin-bottom: 3pt;
    }
    .hs-an-title { font-size: 15pt; font-weight: bold; margin-bottom: 4pt; }
    .hs-an-line { font-size: 8pt; }

    h2 {
        font-size: 12pt;
        font-weight: bold;
        margin: 6pt 0 8pt;
    }

    table { width: 100%; border-collapse: collapse; }

    /* The tiles: one row of boxes. */
    .hs-an-tiles { margin-bottom: 10pt; }
    .hs-an-tiles td {
        border: 0.75pt solid #000;
        padding: 7pt 8pt;
        vertical-align: top;
    }
    .hs-an-tile-label { font-size: 7.5pt; font-weight: bold; }
    .hs-an-tile-value { font-size: 15pt; font-weight: bold; margin: 2pt 0; }
    .hs-an-tile-sub { font-size: 7pt; }

    .hs-an-card {
        border: 0.75pt solid #000;
        border-radius: 6pt;
        padding: 8pt 10pt;
        margin-bottom: 10pt;
        page-break-inside: avoid;
    }
    h3 { font-size: 9.5pt; font-weight: bold; }
    .hs-an-note { font-size: 7.5pt; margin-top: 2pt; }
    .hs-an-footnote { font-size: 7.5pt; margin-top: 5pt; }
    .hs-an-rows { margin-top: 6pt; }
    .hs-an-rows td {
        padding: 2.5pt 4pt;
        border-bottom: 0.25pt solid #000;
        vertical-align: middle;
    }
    .hs-an-rows th {
        padding: 3pt 4pt;
        font-size: 7.5pt;
        font-weight: bold;
        border-bottom: 1pt solid #000;
    }
    .hs-an-num { text-align: right; white-space: nowrap; }
    .hs-an-bar { height: 7pt; background-color: #000; }
</style>
</head>
<body>
    <div class="hs-an-header">
        <div class="hs-an-eyebrow">Harbor Safe · Analytics</div>
        <div class="hs-an-title">{{ $title }}</div>
        @foreach ($lines as $line)
            <div class="hs-an-line">{{ $line }}</div>
        @endforeach
    </div>

    @foreach ($sections as $section)
        @if (filled($section['heading'] ?? null))
            <h2>{{ $section['heading'] }}</h2>
        @endif

        {{-- dompdf ignores <col>, so the cells carry the widths (less padding). --}}
        @php $tileWidth = floor($width / max(1, count($section['tiles']))) - 18; @endphp
        <table class="hs-an-tiles">
            <tr>
                @foreach ($section['tiles'] as $tile)
                    <td style="width: {{ $tileWidth }}pt">
                        <div class="hs-an-tile-label">{{ $tile['label'] }}</div>
                        <div class="hs-an-tile-value">{{ $tile['value'] }}</div>
                        @if (filled($tile['sub']))
                            <div class="hs-an-tile-sub">{{ $tile['sub'] }}</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>

        @foreach ($section['cards'] as $card)
            <div class="hs-an-card">
                <h3>{{ $card['title'] }}</h3>
                @if (filled($card['note']))
                    <p class="hs-an-note">{{ $card['note'] }}</p>
                @endif

                @if ($card['type'] === 'table')
                    @php $numberWidth = floor(($width - 20 - 120) / max(1, count($card['columns']))) - 8; @endphp
                    <table class="hs-an-rows">
                        <tr>
                            <th style="width: 112pt; text-align: left">{{ $card['firstColumn'] }}</th>
                            @foreach ($card['columns'] as $column)
                                <th class="hs-an-num" style="width: {{ $numberWidth }}pt; white-space: normal">{{ $column }}</th>
                            @endforeach
                        </tr>
                        @foreach ($card['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                @foreach ($row['values'] as $value)
                                    <td class="hs-an-num" @if ($loop->last) style="font-weight: bold" @endif>{{ number_format($value) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                @else
                    {{-- Name | bar | number. The bar is the value's share of the
                         card's largest, as on the page. --}}
                    <table class="hs-an-rows">
                        @foreach ($card['bars'] as $bar)
                            <tr>
                                <td @if ($loop->first) style="width: 196pt" @endif>{{ $bar['name'] }}</td>
                                <td @if ($loop->first) style="width: 216pt" @endif>
                                    @if ($bar['pct'] > 0)
                                        <div class="hs-an-bar" style="width: {{ $bar['pct'] }}%"></div>
                                    @endif
                                </td>
                                <td class="hs-an-num" @if ($loop->first) style="width: 64pt" @endif>{{ $bar['display'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if (filled($card['footnote']))
                    <p class="hs-an-footnote">{{ $card['footnote'] }}</p>
                @endif
            </div>
        @endforeach
    @endforeach
</body>
</html>
