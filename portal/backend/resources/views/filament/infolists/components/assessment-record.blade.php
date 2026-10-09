@php
    use App\Filament\Pages\NewAssessment;

    $record   = $getRecord();
    $answers  = $record->assessmentAnswers;

    $yesCount = $answers
        ? collect(array_keys(NewAssessment::QUESTIONS))
            ->filter(fn (int $id): bool => (bool) $answers->{"RiskIndicator{$id}"})
            ->count()
        : 0;

    $sexLabel  = fn (?string $code): string => match ($code) {
        'M' => 'Male', 'F' => 'Female', 'O' => 'Other', default => '—',
    };

    $fmtDate = fn ($val): string => $val
        ? \Carbon\Carbon::parse($val)->format('M j, Y')
        : '—';

    $scoreColor = match (true) {
        $yesCount >= 4 => '#b91c1c',
        $yesCount >= 1 => '#b45309',
        default        => '#6b7280',
    };

    $scoreBg = match (true) {
        $yesCount >= 4 => '#fef2f2',
        $yesCount >= 1 => '#fffbeb',
        default        => '#f9fafb',
    };
@endphp

<div class="hs-assessment-record">

    {{-- Header --}}
    <div class="hs-ar-header">
        <div class="hs-ar-header-eyebrow">Harbor Safe</div>
        <div class="hs-ar-header-title">Lethality Assessment Protocol</div>
        <div class="hs-ar-header-meta">
            {{-- Stored UTC, shown Eastern - see App\Support\Timezones. --}}
            <span>{{ $record->DateCreated ? \Carbon\Carbon::parse($record->DateCreated)->setTimezone(\App\Support\Timezones::DISPLAY)->format('M j, Y \a\t g:i a') : '—' }}</span>
            <span class="hs-ar-header-sep">·</span>
            <span>Officer: {{ $record->submitter?->name ?? 'Unknown' }}</span>
        </div>
        <div class="hs-ar-header-id">Record ID: {{ $record->DocumentID }}</div>
    </div>

    {{-- Victim / Offender --}}
    <div class="hs-ar-parties">

        <div class="hs-ar-party">
            <div class="hs-ar-party-label">Victim</div>
            <dl class="hs-ar-fields">
                <div class="hs-ar-field">
                    <dt>Name</dt>
                    <dd>{{ trim(($record->VictimFirstName ?? '') . ' ' . ($record->VictimLastName ?? '')) ?: '—' }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Sex</dt>
                    <dd>{{ $sexLabel($record->VictimSex) }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Date of birth</dt>
                    <dd>{{ $fmtDate($record->VictimDOB) }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Safe phone</dt>
                    <dd>{{ $record->VictimSafePhoneNumber ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="hs-ar-party">
            <div class="hs-ar-party-label">Offender</div>
            <dl class="hs-ar-fields">
                <div class="hs-ar-field">
                    <dt>Name</dt>
                    <dd>{{ trim(($record->OffenderFirstName ?? '') . ' ' . ($record->OffenderLastName ?? '')) ?: '—' }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Sex</dt>
                    <dd>{{ $sexLabel($record->OffenderSex) }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Date of birth</dt>
                    <dd>{{ $fmtDate($record->OffenderDOB) }}</dd>
                </div>
                <div class="hs-ar-field">
                    <dt>Relationship</dt>
                    <dd>{{ \App\Enums\OffenderRelationship::display($record->OffenderVictimRelationship, $record->OffenderVictimRelationshipOther) ?? '—' }}</dd>
                </div>
            </dl>
        </div>

    </div>

    {{-- Risk indicators --}}
    <div class="hs-ar-risk">
        <div class="hs-ar-risk-header">
            <span class="hs-ar-risk-title">Risk Indicators</span>
            <span class="hs-ar-risk-score" style="color:{{ $scoreColor }}; background-color:{{ $scoreBg }}">
                {{ $yesCount }} of 11 yes
            </span>
        </div>
        <div class="hs-ar-risk-list">
            @foreach (NewAssessment::QUESTIONS as $id => $question)
                @php $yes = $answers && (bool) $answers->{"RiskIndicator{$id}"} @endphp
                <div class="hs-ar-indicator {{ $yes ? 'hs-ar-indicator--yes' : '' }}">
                    <span class="hs-ar-indicator-num {{ $yes ? 'hs-ar-indicator-num--yes' : '' }}">{{ $id }}</span>
                    <span class="hs-ar-indicator-text">{{ $question }}</span>
                    <span class="hs-ar-indicator-badge {{ $yes ? 'hs-ar-indicator-badge--yes' : '' }}">
                        {{ $yes ? 'YES' : 'NO' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

</div>
