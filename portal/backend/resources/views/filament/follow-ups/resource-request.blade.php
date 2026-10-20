{{--
A resource request as its follow-up page shows it: everything the requester
gave, laid out like the assessment record (hs-ar-* styles in theme.css).
--}}
<div class="hs-assessment-record">
    <div class="hs-ar-header">
        <div class="hs-ar-header-eyebrow">Harbor Safe</div>
        <div class="hs-ar-header-title">Resource request</div>
        <div class="hs-ar-header-meta">
            <span>{{ $request->SubmissionDate ? \Carbon\Carbon::parse($request->SubmissionDate)->setTimezone(\App\Support\Timezones::DISPLAY)->format('M j, Y \a\t g:i a') : '—' }}</span>
        </div>
        <div class="hs-ar-header-id">Record ID: {{ $request->FormID }}</div>
    </div>

    <div class="hs-ar-parties">
        <div class="hs-ar-party">
            <div class="hs-ar-party-label">Requester</div>
            <dl class="hs-ar-fields">
                <div class="hs-ar-field">
                    <dt>Name</dt>
                    <dd>{{ trim(($request->FirstName ?? '').' '.($request->LastName ?? '')) ?: '—' }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>County</dt>
                    <dd>{{ $request->county?->Name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="hs-ar-party">
            <div class="hs-ar-party-label">How to reach them</div>
            <dl class="hs-ar-fields">
                <div class="hs-ar-field">
                    <dt>Safe phone</dt>
                    <dd>{{ $request->SafePhoneNumber ?: '—' }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Email</dt>
                    <dd>{{ $request->EmailAddress ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="hs-ar-party">
        <div class="hs-ar-party-label">What they asked for</div>
        <dl class="hs-ar-fields hs-fu-single">
            <div class="hs-ar-field">
                <dt>Resources of interest</dt>
                <dd>{{ $request->resourceTypes->pluck('Name')->implode(', ') ?: 'None selected' }}</dd>
            </div>
            <div class="hs-ar-field">
                <dt>Message</dt>
                <dd class="hs-fu-message">{{ $request->Message ?: 'No message left' }}</dd>
            </div>
        </dl>
    </div>
</div>
