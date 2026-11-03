{{-- Top of the officer Edit pop-up, matching the View pop-up's header. --}}
<div class="hs-assessment-record">
    <div class="hs-ar-header hs-edit-header">
        <div class="hs-ar-header-eyebrow">Harbor Safe</div>
        <div class="hs-ar-header-title">Edit Lethality Assessment</div>
        <div class="hs-ar-header-meta">
            <span>Submitted {{ $submitted ?? '—' }}</span>
            <span class="hs-ar-header-sep">·</span>
            <span>Every change is recorded in the change log with your reason</span>
        </div>
        @if ($record)
            <div class="hs-ar-header-id">Record ID: {{ $record->getKey() }}</div>
        @endif
    </div>
</div>
