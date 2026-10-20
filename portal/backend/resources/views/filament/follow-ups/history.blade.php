{{-- A form's follow-up history, oldest first (App\Filament\FollowUps\FollowUpLog). --}}
<ol class="hs-fu-history">
    @foreach ($steps as $step)
        <li class="hs-fu-step">
            <div class="hs-fu-step-heading">{{ $step['heading'] }}</div>
            <div class="hs-fu-step-meta">
                {{ $step['when'] }}@if ($step['who']) · {{ $step['who'] }}@endif
            </div>
            @if ($step['note'])
                <p class="hs-fu-step-note">{{ $step['note'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
