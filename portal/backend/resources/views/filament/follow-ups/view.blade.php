{{--
One form's follow-up page (App\Filament\Resources\FollowUps\Pages\ViewFollowUp):
the whole form on the left, its follow-up history on the right.
--}}
@php
    $followUp = $this->followUp();
    $form = $followUp->form();
@endphp

<x-filament-panels::page>
    <div class="hs-fu-layout">
        <section class="hs-fu-form">
            @if ($form === null)
                <p class="hs-fu-missing">The form this follow-up belongs to can't be found.</p>
            @elseif ($form instanceof \App\Models\ResourceRequestForm)
                @include(\App\Support\RecordPdf::RESOURCE_REQUEST, ['getRecord' => fn () => $form])
            @else
                @include('filament.infolists.components.assessment-record', ['getRecord' => fn () => $form])
            @endif
        </section>

        <aside class="hs-fu-side">
            <div class="hs-fu-card">
                <h2 class="hs-fu-card-title">Follow-Up</h2>
                <dl class="hs-fu-facts">
                    <div><dt>Status</dt><dd>{{ $followUp->Status->label() }}</dd></div>
                    @if ($started = $this->startedFrom())
                        <div>
                            <dt>Started by staff</dt>
                            <dd>
                                {{ $started['by'] ?? 'Unknown' }}
                                @if ($started['from'])
                                    <br><a href="{{ $started['from']['url'] }}" class="hs-fu-link">From {{ $started['from']['label'] }}</a>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($later = $this->requestsStartedHere())
                        <div>
                            <dt>Requests started from this form</dt>
                            <dd>
                                @foreach ($later as $link)
                                    <a href="{{ $link['url'] }}" class="hs-fu-link">{{ $link['label'] }}</a>@if (! $loop->last)<br>@endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt>Last updated</dt>
                        <dd>
                            @if ($followUp->LastUpdatedAt)
                                {{ $followUp->LastUpdatedAt->copy()->setTimezone(\App\Support\Timezones::DISPLAY)->format('M j, Y g:i a') }}
                                · {{ $followUp->lastUpdatedBy?->name ?? 'Unknown' }}
                            @else
                                Not yet
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="hs-fu-card">
                <h2 class="hs-fu-card-title">Log</h2>
                @include(\App\Filament\FollowUps\FollowUpLog::VIEW, ['steps' => $this->historySteps()])
            </div>
        </aside>
    </div>
</x-filament-panels::page>
