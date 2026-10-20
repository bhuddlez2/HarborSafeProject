@php
    use App\Models\ServiceFeedback;
    use App\Support\Timezones;

    $record  = $getRecord();
    // In the PDF: the form only - no source, staff name or scan (they show on
    // screen; App\Support\RecordPdf).
    $isPdf   = $isPdf ?? false;
    $isStaff = $record->Source === ServiceFeedback::SOURCE_STAFF;
    $rating  = $record->Rating;

    [$ratingColor, $ratingBg] = match (true) {
        $rating === null => ['#6b7280', '#f9fafb'],
        $rating >= 4     => ['#15803d', '#f0fdf4'],
        $rating === 3    => ['#b45309', '#fffbeb'],
        default          => ['#b91c1c', '#fef2f2'],
    };
@endphp

<div class="hs-assessment-record">

    <div class="hs-ar-header">
        <div class="hs-ar-header-eyebrow">Harbor Safe</div>
        <div class="hs-ar-header-title">Service Feedback</div>
        <div class="hs-ar-header-meta">
            {{-- Stored UTC, shown Eastern - see App\Support\Timezones. --}}
            <span>{{ $record->SubmissionDate ? \Carbon\Carbon::parse($record->SubmissionDate)->setTimezone(Timezones::DISPLAY)->format('M j, Y \a\t g:i a') : '—' }}</span>
            {{-- How it arrived and who entered it: on screen only, never in the
                 PDF (AS, 2026-10-20: no staff-entry or log information on an export). --}}
            @unless ($isPdf)
                <span class="hs-ar-header-sep">·</span>
                <span>{{ $isStaff ? 'Entered by staff' : 'Submitted online' }}</span>
            @endunless
        </div>
        <div class="hs-ar-header-id">Record ID: {{ $record->getKey() }}</div>
    </div>

    <div class="hs-ar-party hs-ar-card">
        <div class="hs-ar-party-label">Feedback</div>
        <dl class="hs-ar-fields hs-ar-fields--3">
            <div class="hs-ar-field">
                <dt>Service</dt>
                <dd>{{ $record->service?->Name ?? '—' }}</dd>
            </div>
            <div class="hs-ar-field">
                <dt>Rating</dt>
                <dd>
                    <span class="hs-ar-pill" style="color:{{ $ratingColor }}; background-color:{{ $ratingBg }}">
                        {{ $rating === null ? 'Not recorded' : $rating.' out of 5' }}
                    </span>
                </dd>
            </div>
            @unless ($isPdf)
            <div class="hs-ar-field">
                <dt>Source</dt>
                <dd>{{ $isStaff ? 'Staff' : 'Online' }}</dd>
            </div>
            @endunless
            @if ($isStaff && ! $isPdf)
                <div class="hs-ar-field">
                    <dt>Entered by</dt>
                    <dd>{{ $record->enteredByName() ?? 'Unknown' }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Scan of the form</dt>
                    <dd>
                        @if (! filled($record->ScanFileID))
                            None attached
                        @elseif ($isPdf)
                            Attached (open it in the panel)
                        @else
                            <a class="hs-ar-link" href="{{ route('staff.feedback-scans.show', ['file' => $record->ScanFileID]) }}" target="_blank" rel="noopener">Open the scan</a>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="hs-ar-party hs-ar-card">
        <div class="hs-ar-party-label">Comment</div>
        @if (filled($record->Comment))
            <p class="hs-ar-text">{{ $record->Comment }}</p>
        @else
            <p class="hs-ar-text hs-ar-text--empty">No comment left.</p>
        @endif
    </div>

</div>
