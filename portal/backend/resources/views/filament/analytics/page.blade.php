{{-- One Analytics tab (App\Filament\Analytics\AnalyticsPage): the date range
     and filter, then each section's tiles and chart cards. Totals only. --}}
<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        @include('filament.analytics.controls')

        @foreach ($sections as $section)
            <section class="flex flex-col gap-4">
                @if (filled($section['heading']))
                    <h2 class="font-montserrat text-xl font-bold text-gray-900">{{ $section['heading'] }}</h2>
                @endif

                <div class="grid grid-cols-[repeat(auto-fit,minmax(13rem,1fr))] gap-4">
                    @foreach ($section['tiles'] as $tile)
                        <div class="flex flex-col gap-1 rounded-xl border border-gray-200 bg-white px-5 py-4">
                            <p class="text-sm font-semibold text-gray-600">{{ $tile['label'] }}</p>
                            <p class="font-montserrat text-[28px] font-bold leading-tight text-gray-900 tabular-nums">{{ $tile['value'] }}</p>
                            @if (filled($tile['sub']))
                                <p class="text-[13px] text-gray-500">{{ $tile['sub'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @foreach ($section['cards'] as $card)
                        @include('filament.analytics.card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
