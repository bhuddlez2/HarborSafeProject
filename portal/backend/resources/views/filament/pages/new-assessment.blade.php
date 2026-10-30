{{--
    Carried over from portal/frontend/app/police/portal/page.js. Each phase
    there was a white card centred on a gray-100 screen; here the wizard
    itself is that card (see NewAssessment::form()).
--}}
<x-filament-panels::page class="hs-officer-page">
    {{-- Tighter padding on phones so the form keeps its width at 375 px (team plan task 10). --}}
    <main class="min-h-dvh bg-[#F4F2F7] flex items-start md:items-center justify-center p-3 md:p-6">
        <div class="w-full max-w-2xl">
            @if ($submitted)
                {{-- Submitted phase --}}
                <div class="bg-white rounded-2xl shadow-lg w-full px-5 py-8 md:px-12 md:py-14">
                    <h1 class="text-3xl font-semibold text-gray-900 mb-6">
                        Assessment Submitted
                    </h1>
                    <p class="text-gray-700 text-lg mb-8 leading-relaxed">
                        The assessment has been saved to the assessment record.
                    </p>

                    <div class="mb-10">
                        @include('filament.pages.new-assessment.hotlines')
                    </div>
                    {{-- Back to Home goes straight to the Home page (team plan item 8); side by side, stacked on phones. --}}
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            wire:click="startNewAssessment"
                            class="bg-[#5C0F8B] text-white px-8 py-4 rounded-lg text-lg
                                   hover:bg-[#4C0B74] focus:outline-none
                                   focus:ring-4 focus:ring-[#5C0F8B]/40 transition"
                        >
                            Start new assessment
                        </button>
                        <a
                            href="{{ \App\Filament\Pages\Police::getUrl() }}"
                            class="flex items-center justify-center gap-2 border-2 border-[#5C0F8B] bg-white text-[#5C0F8B] px-8 py-4 rounded-lg text-lg
                                   hover:bg-[#F5F0FB] focus:outline-none
                                   focus:ring-4 focus:ring-[#5C0F8B]/40 transition"
                        >
                            <x-officer-icon name="home" :size="20" />
                            Back to Home
                        </a>
                    </div>
                </div>
            @else
                {{ $this->form }}
            @endif
        </div>
    </main>

    {{-- Keeps an unfinished assessment in this tab through the idle logout. --}}
    @include('filament.pages.new-assessment.draft')
</x-filament-panels::page>
