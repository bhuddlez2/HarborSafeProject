{{--
    History of a law-enforcement assessment: a header naming the record, then
    newest first either
      - one card per edit ($edits: the "Change log" button on My Assessments,
        and the Change log page's View via $getState()), or
      - one timeline ($events: the "Log" button on Assessment review, team
        plan task 22) - edits as the same cards, views and PDF downloads as
        compact rows, the submission at the bottom.
    Data comes from App\Filament\Assessments\ChangeHistory - already labelled
    and formatted, so nothing here interprets raw values.
--}}
@php
    $state = isset($getState) ? ($getState() ?? []) : [];
    $header ??= $state['header'] ?? null;
    $edits ??= $state['edits'] ?? [];

    // Without a timeline, the edits are the whole list.
    $isTimeline = isset($events);
    $events ??= array_map(fn (array $edit): array => ['kind' => 'edit', ...$edit], $edits);

    $editTotal = collect($events)->where('kind', 'edit')->count();
    $editNumber = $editTotal;
    $hasViews = $isTimeline && isset($header['views']);
@endphp

<div class="hs-assessment-record">

    @if ($header)
        <div class="hs-ar-header">
            <div class="hs-ar-header-eyebrow">Harbor Safe</div>
            <div class="hs-ar-header-title">{{ $header['title'] ?? 'Change Log' }}</div>
            <div class="hs-ar-header-meta">
                <span>Victim: {{ $header['victim'] }}</span>
                <span class="hs-ar-header-sep">·</span>
                <span>Offender: {{ $header['offender'] }}</span>
            </div>
            <div class="hs-ar-header-meta hs-cl-header-sub">
                <span>Submitted {{ $header['submitted'] }}</span>
                @if ($header['edits'] !== null)
                    <span class="hs-ar-header-sep">·</span>
                    <span>{{ $header['edits'] }} {{ str('edit')->plural($header['edits']) }}</span>
                @endif
                @if ($hasViews)
                    <span class="hs-ar-header-sep">·</span>
                    <span>{{ $header['views'] }} {{ $header['views'] === 1 ? 'view or download' : 'views or downloads' }}</span>
                @endif
            </div>
        </div>
        <p class="hs-cl-notice">
            {{ $isTimeline
                ? 'Every view, PDF download and edit, newest first. None can be changed or removed.'
                : 'Edits are listed newest first and cannot be changed or removed.' }}
        </p>
    @endif

    <div class="hs-cl-list">
        @forelse ($events as $event)
            @if ($event['kind'] === 'edit')
                <article class="hs-ar-party hs-cl-edit">
                    <header class="hs-cl-edit-head">
                        <div>
                            @if ($header && $header['edits'] !== null)
                                <span class="hs-ar-party-label">Edit {{ $editNumber-- }}</span>
                            @else
                                <span class="hs-ar-party-label">This edit</span>
                            @endif
                            <p class="hs-cl-editor">{{ $event['editor'] }}</p>
                        </div>
                        <p class="hs-cl-when">{{ $event['when'] }}</p>
                    </header>

                    <div class="hs-cl-reason">
                        <span class="hs-cl-reason-label">Reason</span>
                        <p>{{ $event['reason'] }}</p>
                    </div>

                    <table class="hs-cl-changes">
                        <thead>
                            <tr>
                                <th scope="col">Field</th>
                                <th scope="col">Before</th>
                                <th scope="col">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($event['changes'] as $change)
                                <tr>
                                    <th scope="row">{{ $change['label'] }}</th>
                                    <td><span class="hs-cl-before">{{ $change['from'] }}</span></td>
                                    <td><span class="hs-cl-after">{{ $change['to'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @else
                {{-- A view, a PDF download, or the submission: one line. --}}
                <div class="hs-cl-event hs-cl-event--{{ $event['kind'] }}">
                    <span class="hs-cl-event-icon" aria-hidden="true">
                        @switch($event['kind'])
                            @case('download')
                            @case('download_with_log')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                                @break
                            @case('submitted')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                        @endswitch
                    </span>
                    <p class="hs-cl-event-text"><span class="hs-cl-event-label">{{ $event['label'] }}</span> by {{ $event['who'] }}</p>
                    <p class="hs-cl-when">{{ $event['when'] }}</p>
                </div>
            @endif
        @empty
            <p class="hs-ar-text hs-ar-text--empty">No edits since submission.</p>
        @endforelse
    </div>

</div>
