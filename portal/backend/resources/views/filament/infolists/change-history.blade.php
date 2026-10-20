{{--
    Change history of a law-enforcement assessment: a header naming the record,
    then one card per edit, newest first. Data comes from
    App\Filament\Assessments\ChangeHistory - already labelled and formatted, so
    nothing here interprets raw values. Rendered two ways: as the "Change log"
    button's pop-up ($header and $edits passed in) and as an infolist entry on
    the Change log page ($getState()).
--}}
@php
    $state = isset($getState) ? ($getState() ?? []) : [];
    $edits ??= $state['edits'] ?? [];
    $header ??= $state['header'] ?? null;
    $total = count($edits);
@endphp

<div class="hs-assessment-record">

    @if ($header)
        <div class="hs-ar-header">
            <div class="hs-ar-header-eyebrow">Harbor Safe</div>
            <div class="hs-ar-header-title">Change Log</div>
            <div class="hs-ar-header-meta">
                <span>Victim: {{ $header['victim'] }}</span>
                <span class="hs-ar-header-sep">·</span>
                <span>Offender: {{ $header['offender'] }}</span>
            </div>
            <div class="hs-ar-header-meta hs-cl-header-sub">
                <span>Submitted {{ $header['submitted'] }}</span>
                @if ($header['edits'] !== null)
                    <span class="hs-ar-header-sep">·</span>
                    <span>{{ $header['edits'] }} {{ str('edit')->plural($header['edits']) }} since</span>
                @endif
            </div>
        </div>
        <p class="hs-cl-notice">Edits are listed newest first and cannot be changed or removed.</p>
    @endif

    <div class="hs-cl-list">
        @forelse ($edits as $index => $edit)
            <article class="hs-ar-party hs-cl-edit">
                <header class="hs-cl-edit-head">
                    <div>
                        @if ($header && $header['edits'] !== null)
                            <span class="hs-ar-party-label">Edit {{ $total - $index }}</span>
                        @else
                            <span class="hs-ar-party-label">This edit</span>
                        @endif
                        <p class="hs-cl-editor">{{ $edit['editor'] }}</p>
                    </div>
                    <p class="hs-cl-when">{{ $edit['when'] }}</p>
                </header>

                <div class="hs-cl-reason">
                    <span class="hs-cl-reason-label">Reason</span>
                    <p>{{ $edit['reason'] }}</p>
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
                        @foreach ($edit['changes'] as $change)
                            <tr>
                                <th scope="row">{{ $change['label'] }}</th>
                                <td><span class="hs-cl-before">{{ $change['from'] }}</span></td>
                                <td><span class="hs-cl-after">{{ $change['to'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </article>
        @empty
            <p class="hs-ar-text hs-ar-text--empty">No edits since submission.</p>
        @endforelse
    </div>

</div>
