{{-- Carried over from portal/frontend/app/police/page.js: same copy, classes and order. --}}
@php
    $draft = $this->getOpenDraft();
    $minutes = $this->getSessionTimeoutMinutes();
@endphp

<x-filament-panels::page class="hs-officer-page">
    <div class="mx-auto flex w-full max-w-250 flex-col gap-7 px-5 py-8 md:px-14 md:py-12">

        <div class="flex items-end justify-between">
            <h1 class="font-montserrat text-2xl font-bold md:text-[32px]">
                What do you need to do?
            </h1>
            {{-- Desktop only — on mobile this appears below the card stack --}}
            <span class="hidden md:flex">
                @include('filament.partials.session-notice', ['minutes' => $minutes])
            </span>
        </div>

        @if ($this->isOfficer())
            <div class="grid gap-5 md:grid-cols-3">

                {{-- Primary action — start a new assessment --}}
                <a
                    href="{{ \App\Filament\Pages\NewAssessment::getUrl() }}"
                    class="flex min-h-55 flex-col justify-end gap-4 rounded-[18px] bg-[#5C0F8B] p-6 text-white
                           transition-colors hover:bg-[#4C0B74]
                           focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5C0F8B]
                           md:col-span-2 md:min-h-70 md:p-9"
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

                <div class="flex flex-col gap-5">

                    {{-- Draft / no-draft card --}}
                    @if ($draft)
                        <section
                            aria-labelledby="draft-heading"
                            class="flex grow flex-col gap-3 rounded-2xl border border-[#DDD7E6] bg-white p-5.5"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h2 id="draft-heading" class="text-[17px] font-bold">
                                    Unfinished draft
                                </h2>
                                <span class="rounded-full bg-[#FFF1D6] px-2.5 py-1 text-[13px] font-bold text-[#7A4A00]">
                                    Draft
                                </span>
                            </div>
                            <p class="text-[15px] text-[#5A5566]">
                                Case {{ $draft['caseNumber'] }} &middot; started {{ $draft['startedAt'] }}
                            </p>
                            <div class="grow"></div>
                            <div class="flex gap-2.5">
                                <a
                                    href="{{ \App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource::getUrl('index') }}"
                                    class="flex h-12 grow items-center justify-center rounded-[10px] bg-[#231A33] text-base font-bold text-white
                                           transition-colors hover:bg-[#180F26]
                                           focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
                                >
                                    Resume
                                </a>
                                {{-- TODO: wire to an action that soft-deletes the draft --}}
                                <button
                                    type="button"
                                    class="h-12 rounded-[10px] border border-[#C9C1D6] px-4 text-base font-semibold
                                           transition-colors hover:bg-[#F4F2F7]
                                           focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
                                >
                                    Discard
                                </button>
                            </div>
                        </section>
                    @else
                        <section class="flex grow flex-col justify-center gap-2 rounded-2xl border border-dashed border-[#C9C1D6] bg-white p-5.5">
                            <h2 class="text-[17px] font-bold">No unfinished drafts</h2>
                            <p class="text-[15px] text-[#5A5566]">
                                A screening you leave part-way through will be saved here.
                            </p>
                        </section>
                    @endif

                </div>
            </div>
        @endif

        {{-- Mobile only — session notice sits below the card stack where it's easy to read --}}
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
                href="{{ \App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource::getUrl('index') }}"
                class="flex h-12 shrink-0 items-center justify-center rounded-[10px] border border-[#C9C1D6] px-5 text-[15px] font-bold
                       transition-colors hover:bg-[#F4F2F7]
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#231A33]"
            >
                Go to My Assessments
            </a>
        </section>

    </div>
</x-filament-panels::page>
