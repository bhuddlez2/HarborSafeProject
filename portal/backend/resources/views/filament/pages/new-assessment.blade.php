{{--
    Carried over from portal/frontend/app/police/portal/page.js. Each phase
    there was a white card centred on a gray-100 screen; here the wizard
    itself is that card (see NewAssessment::form()).
--}}
<x-filament-panels::page class="hs-officer-page">
    <main class="min-h-dvh bg-gray-100 flex items-start md:items-center justify-center p-6">
        <div class="w-full max-w-2xl">
            @if ($submitted)
                {{-- Submitted phase --}}
                <div class="bg-white rounded-2xl shadow-lg w-full px-8 py-10 md:px-12 md:py-14">
                    <h1 class="text-3xl font-semibold text-gray-900 mb-6">
                        Assessment submitted
                    </h1>
                    <p class="text-gray-700 text-lg mb-10 leading-relaxed">
                        The assessment has been saved successfully.
                    </p>
                    <button
                        type="button"
                        wire:click="startNewAssessment"
                        class="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg
                               hover:bg-gray-700 focus:outline-none
                               focus:ring-4 focus:ring-gray-400 transition"
                    >
                        Start new assessment
                    </button>
                </div>
            @else
                {{ $this->form }}
            @endif
        </div>
    </main>
</x-filament-panels::page>
