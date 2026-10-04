{{--
    Questions phase: one question per screen, Yes/No moves straight on, and
    answering the last one advances the wizard to Review & submit.
    requestNextStep() belongs to Filament's wizard Alpine component, which
    also validates that every RiskIndicator has an answer.
--}}
@php
    $total = count($questions);
    $percent = (int) round((($index + 1) / $total) * 100);
    $text = array_values($questions)[$index];
@endphp

<div>
    {{-- Progress bar --}}
    <div class="mb-10">
        <div class="flex justify-between text-sm text-gray-500 mb-2">
            <span>Question {{ $index + 1 }} of {{ $total }}</span>
            <span>{{ $percent }}%</span>
        </div>
        <div
            class="h-2 bg-gray-200 rounded-full overflow-hidden"
            role="progressbar"
            aria-valuenow="{{ $percent }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full bg-[#5C0F8B] transition-all duration-300"
                style="width: {{ $percent }}%"
            ></div>
        </div>
    </div>

    {{-- Question text --}}
    <h2
        class="text-2xl md:text-3xl text-gray-900 font-medium leading-snug mb-10"
        aria-live="polite"
    >
        {{ $text }}
    </h2>

    @foreach (['hidden md:block', 'md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-6 pb-10 pt-4'] as $wrapperClass)
        {{-- Back + Yes/No: inside the card on desktop, fixed to the bottom on mobile --}}
        <div class="{{ $wrapperClass }}">
            <div @class(['mx-auto max-w-2xl' => str_contains($wrapperClass, 'fixed')])>
                @if ($index > 0)
                    <button
                        type="button"
                        wire:click="previousQuestion"
                        class="text-sm text-gray-500 hover:text-[#5C0F8B] transition block mb-4"
                    >
                        Previous question
                    </button>
                @endif
                <div class="flex gap-4">
                    @foreach (['Yes' => 'true', 'No' => 'false'] as $label => $value)
                        <button
                            type="button"
                            x-on:click="$wire.answerQuestion({{ $value }}).then((isLast) => isLast && requestNextStep())"
                            wire:loading.attr="disabled"
                            class="flex-1 border-2 border-[#5C0F8B] text-[#5C0F8B] text-lg py-4 rounded-lg
                                   hover:bg-[#5C0F8B] hover:text-white
                                   focus:outline-none focus-visible:ring-4 focus-visible:ring-[#5C0F8B]/40 transition"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
