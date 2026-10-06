@php
    $minutes = $this->getSessionTimeoutMinutes();
@endphp

<x-filament-panels::page class="hs-officer-page">
    <div class="mx-auto flex w-full max-w-250 flex-col gap-7 px-5 py-8 md:px-14 md:py-12">

        <div class="flex items-end justify-between">
            <h1 class="font-montserrat text-2xl font-bold md:text-[32px]">
                What do you need to do?
            </h1>
            {{-- Desktop only -- on mobile this appears below the card stack --}}
            <span class="hidden md:flex">
                @include('filament.partials.session-notice', ['minutes' => $minutes])
            </span>
        </div>

        @if ($this->isOfficer())
            <a
                href="{{ \App\Filament\Pages\NewAssessment::getUrl() }}"
                class="flex min-h-55 flex-col justify-end gap-4 rounded-[18px] bg-[#5C0F8B] p-6 text-white
                       transition-colors hover:bg-[#4C0B74]
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5C0F8B]
                       md:min-h-70 md:p-9"
            >
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 p-4">
                    <x-officer-icon name="plus" :size="32" />
                </span>
                <span class="font-montserrat text-[26px] font-bold leading-tight md:text-[34px]">
                    Start New Lethality Assessment
                </span>
                <span class="text-[17px] text-[#EDE3F5]">
                    Opens a blank LAP screening form
                </span>
            </a>
        @endif

        {{-- Mobile only -- session notice sits below the card stack where it's easy to read --}}
        <span class="md:hidden">
            @include('filament.partials.session-notice', ['minutes' => $minutes])
        </span>

        {{-- Pointer to past records --}}
        <section class="flex flex-col gap-4 rounded-2xl border border-[#DDD7E6] bg-white px-5.5 py-5.5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-[17px] font-bold">Looking for a past assessment?</h2>
                <p class="text-[15px] text-[#5A5566]">
                    Records are in My Assessments. Each view is logged with your name and a timestamp.
                </p>
            </div>
            <a
                href="{{ $this->pastAssessmentsUrl() }}"
                class="flex h-12 shrink-0 items-center justify-center rounded-[10px] border border-[#C9C1D6] px-5 text-[15px] font-bold
                       transition-colors hover:bg-[#F4F2F7]
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
            >
                Go to My Assessments
            </a>
        </section>

    </div>
</x-filament-panels::page>
