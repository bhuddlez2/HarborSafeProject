<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LAP Assessment</title>
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
        font-size: 14px;
        color: #1b1726;
        background: #fff;
        padding: 2rem;
    }

    /* Header */
    .hs-ar-header {
        background-color: #5c0f8b;
        color: #fff;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    .hs-ar-header-eyebrow {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        opacity: 0.7;
        margin-bottom: 4px;
    }
    .hs-ar-header-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .hs-ar-header-meta {
        font-size: 13px;
        opacity: 0.85;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .hs-ar-header-sep { opacity: 0.5; }
    .hs-ar-header-id {
        font-size: 11px;
        opacity: 0.6;
        margin-top: 6px;
        font-family: 'Courier New', monospace;
    }

    /* Victim / Offender */
    .hs-ar-parties {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .hs-ar-party {
        background-color: #f5f0fb;
        border-radius: 12px;
        padding: 1rem 1.25rem;
    }
    .hs-ar-party-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #5c0f8b;
        margin-bottom: 12px;
    }
    .hs-ar-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 12px;
    }
    .hs-ar-field {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .hs-ar-field dt {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9068b8;
    }
    .hs-ar-field dd {
        font-size: 13px;
        font-weight: 600;
        color: #1b1726;
        overflow-wrap: break-word;
        word-break: break-word;
    }

    /* Risk indicators */
    .hs-ar-risk {
        border: 2px solid #c9c1d6;
        border-radius: 12px;
        overflow: hidden;
    }
    .hs-ar-risk-header {
        background-color: #ede3f5;
        padding: 10px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .hs-ar-risk-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #5c0f8b;
    }
    .hs-ar-risk-score {
        font-size: 13px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
    }
    .hs-ar-indicator {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 16px;
        border-bottom: 1px solid #ede3f5;
    }
    .hs-ar-indicator:last-child { border-bottom: none; }
    .hs-ar-indicator--yes { background-color: #fff5f5; }
    .hs-ar-indicator-num {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        margin-top: 2px;
        background-color: #e5e7eb;
        color: #6b7280;
    }
    .hs-ar-indicator-num--yes { background-color: #fee2e2; color: #b91c1c; }
    .hs-ar-indicator-text {
        flex: 1;
        font-size: 13px;
        color: #374151;
        line-height: 1.5;
    }
    .hs-ar-indicator-badge {
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        padding: 3px 8px;
        border-radius: 6px;
        background-color: #f3f4f6;
        color: #6b7280;
    }
    .hs-ar-indicator-badge--yes { background-color: #fee2e2; color: #b91c1c; }

</style>
</head>
<body>
@include('filament.infolists.components.assessment-record')
</body>
</html>
