@php
    $minutes = $this->getSessionTimeoutMinutes();
    $firstName = \Filament\Facades\Filament::auth()->user()?->first_name;

    // Every card under Start New Lethality Assessment shares one design
    // (team plan item 5): light purple with a purple outline.
    $lightCard = 'flex min-h-55 flex-col justify-end gap-4 rounded-[18px] border-2 border-[#5C0F8B] bg-[#EDE3F5] p-6 text-[#5C0F8B]
                  transition-colors hover:bg-[#E2D3EF]
                  focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5C0F8B]
                  md:min-h-70 md:p-9';
@endphp

<x-filament-panels::page class="hs-officer-page">
    <div class="mx-auto flex w-full max-w-250 flex-col gap-7 px-5 py-8 md:px-14 md:py-12">

        <div class="flex items-end justify-between">
            <div>
                <h1 class="font-montserrat text-2xl font-bold md:text-[32px]">
                    {{ filled($firstName) ? "Welcome back, {$firstName}" : 'Welcome back' }}
                </h1>
                <p class="mt-1 text-base text-[#5A5566] md:text-lg">
                    What would you like to do today?
                </p>
            </div>
            {{-- Desktop only -- on mobile this appears below the card stack --}}
            <span class="hidden md:flex">
                @include('filament.partials.session-notice', ['minutes' => $minutes])
            </span>
        </div>

        @if ($this->canStartAssessment())
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

        {{-- Police admins: their two jobs, one card each (team plan task 11). --}}
        @if ($this->isPoliceAdmin())
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <a href="{{ $this->assessmentReviewUrl() }}" class="{{ $lightCard }}">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#5C0F8B]/10 p-4">
                        <x-officer-icon name="clipboard" :size="32" />
                    </span>
                    <span class="font-montserrat text-[26px] font-bold leading-tight md:text-[30px]">
                        View Assessments
                    </span>
                    <span class="text-[17px] text-[#5A5566]">
                        Your agency's submitted LAP screenings and their change logs
                    </span>
                </a>

                <a href="{{ $this->officersUrl() }}" class="{{ $lightCard }}">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#5C0F8B]/10 p-4">
                        <x-officer-icon name="users" :size="32" />
                    </span>
                    <span class="font-montserrat text-[26px] font-bold leading-tight md:text-[30px]">
                        Personnel Management
                    </span>
                    <span class="text-[17px] text-[#5A5566]">
                        Add and manage your agency's officer accounts
                    </span>
                </a>
            </div>
        @endif

        {{-- Officers: their past records, in the same card as the police admin's View Assessments. --}}
        @if ($this->isOfficer())
            <a href="{{ $this->pastAssessmentsUrl() }}" class="{{ $lightCard }}">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#5C0F8B]/10 p-4">
                    <x-officer-icon name="clipboard" :size="32" />
                </span>
                <span class="font-montserrat text-[26px] font-bold leading-tight md:text-[30px]">
                    View Assessments
                </span>
                <span class="text-[17px] text-[#5A5566]">
                    Your submitted LAP screenings. Each view is logged with your name and a timestamp.
                </span>
            </a>
        @endif

        {{-- Mobile only -- session notice sits below the card stack where it's easy to read --}}
        <span class="md:hidden">
            @include('filament.partials.session-notice', ['minutes' => $minutes])
        </span>

    </div>
</x-filament-panels::page>
