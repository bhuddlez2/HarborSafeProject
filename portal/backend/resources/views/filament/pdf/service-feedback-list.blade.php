<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service feedback</title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11px;
        color: #1b1726;
        background: #fff;
    }

    .hs-fl-header {
        background-color: #5c0f8b;
        color: #fff;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }
    .hs-fl-eyebrow {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        opacity: 0.7;
        margin-bottom: 4px;
    }
    .hs-fl-title {
        font-size: 18px;
        font-weight: 700;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }
    /* Column headings repeat at the top of every printed page. */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th {
        background: #f3f0f7;
        color: #3b2a4d;
        font-size: 10px;
        font-weight: 700;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 6px 8px;
        border-bottom: 2px solid #d8cfe3;
    }
    td {
        padding: 6px 8px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }
    td.hs-fl-nowrap { white-space: nowrap; }
    td.hs-fl-centre, th.hs-fl-centre { text-align: center; }
    td.hs-fl-comment {
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
</head>
<body>
    <div class="hs-fl-header">
        <div class="hs-fl-eyebrow">Harbor Safe</div>
        <div class="hs-fl-title">Service feedback</div>
    </div>

    {{-- Only what each form holds: no filters, export date or names (ServiceFeedbackExport). --}}
    <table>
        <thead>
            <tr>
                @foreach ($columns as $index => $column)
                    <th @class(['hs-fl-centre' => $index === 2])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as [$submitted, $service, $rating, $comment])
                <tr>
                    <td class="hs-fl-nowrap">{{ $submitted?->format('Y-m-d') }}</td>
                    <td>{{ $service }}</td>
                    <td class="hs-fl-centre">{{ $rating }}</td>
                    <td class="hs-fl-comment">{{ $comment }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
