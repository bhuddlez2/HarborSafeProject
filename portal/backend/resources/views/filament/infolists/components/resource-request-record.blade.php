@php
    use App\Support\Timezones;

    $record    = $getRecord();
    $resources = $record->resourceTypes->sortBy('Name');

    // On screen only: a request staff started from an earlier form says so,
    // and who. Never in the PDF - no log or staff-entry information on any
    // export (App\Support\RecordPdf, isPdf).
    $isPdf     = $isPdf ?? false;
    $isStaff   = ! $isPdf && $record->isStaffEntry();

    // On screen the email opens the computer's own email app with the address
    // in To and nothing else: no subject, since subject lines show in inbox
    // previews someone else may see. Plain text in the PDF, and for anything
    // that isn't a valid address (checked when submitted; this is a backstop).
    $email     = $record->EmailAddress;
    $emailLink = ! $isPdf && filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
@endphp

<div class="hs-assessment-record">

    <div class="hs-ar-header">
        <div class="hs-ar-header-eyebrow">Harbor Safe</div>
        <div class="hs-ar-header-title">Resource Request</div>
        <div class="hs-ar-header-meta">
            {{-- Stored UTC, shown Eastern - see App\Support\Timezones. --}}
            <span>{{ $record->SubmissionDate ? \Carbon\Carbon::parse($record->SubmissionDate)->setTimezone(Timezones::DISPLAY)->format('M j, Y \a\t g:i a') : '—' }}</span>
            <span class="hs-ar-header-sep">·</span>
            <span>{{ $record->county?->Name ? $record->county->Name.' County' : 'No county given' }}</span>
            @if ($isStaff)
                <span class="hs-ar-header-sep">·</span>
                <span>Staff · entered by {{ $record->enteredByName() ?? 'Unknown' }}</span>
            @endif
        </div>
        <div class="hs-ar-header-id">Record ID: {{ $record->getKey() }}</div>
    </div>

    <div class="hs-ar-party hs-ar-card">
        <div class="hs-ar-party-label">Who asked</div>
        <dl class="hs-ar-fields hs-ar-fields--3">
            <div class="hs-ar-field">
                <dt>Name</dt>
                <dd>{{ trim(($record->FirstName ?? '').' '.($record->LastName ?? '')) ?: '—' }}</dd>
            </div>
            <div class="hs-ar-field">
                <dt>Email</dt>
                <dd>
                    @if ($emailLink)
                        <a href="mailto:{{ $email }}" class="hs-ar-link">{{ $email }}</a>
                    @else
                        {{ $email ?: '—' }}
                    @endif
                </dd>
            </div>
            <div class="hs-ar-field">
                <dt>Safe phone</dt>
                <dd>{{ $record->SafePhoneNumber ?: '—' }}</dd>
            </div>
        </dl>
        {{-- "Safe" is the operative word - see ResourceRequestResource. --}}
        @if (filled($record->SafePhoneNumber))
            <p class="hs-ar-note">The safe phone is the number they told us is safe to call. Other numbers may not be.</p>
        @endif
    </div>

    <div class="hs-ar-party hs-ar-card">
        <div class="hs-ar-party-label">Resources of interest</div>
        @if ($resources->isNotEmpty())
            <div class="hs-ar-chips">
                @foreach ($resources as $resource)
                    <span class="hs-ar-chip">{{ $resource->Name }}</span>
                @endforeach
            </div>
        @else
            <p class="hs-ar-text hs-ar-text--empty">None selected.</p>
        @endif
    </div>

    <div class="hs-ar-party hs-ar-card">
        <div class="hs-ar-party-label">Message</div>
        @if (filled($record->Message))
            <p class="hs-ar-text">{{ $record->Message }}</p>
        @else
            <p class="hs-ar-text hs-ar-text--empty">No message left.</p>
        @endif
    </div>

</div>
