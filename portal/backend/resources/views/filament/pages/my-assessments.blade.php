{{--
    My Assessments -- static shell for teammate review.
    Fake rows, non-functional search/filter/buttons.
    Statuses: Submitted and Amended only
--}}
@php
    $minutes = $this->getSessionTimeoutMinutes();

    $rows = [
        ['case' => 'LAP-2026-001', 'date' => 'Sep 12, 2026', 'status' => 'Submitted'],
        ['case' => 'LAP-2026-002', 'date' => 'Sep 18, 2026', 'status' => 'Amended'],
        ['case' => 'LAP-2026-003', 'date' => 'Sep 25, 2026', 'status' => 'Submitted'],
        ['case' => 'LAP-2026-004', 'date' => 'Oct 1, 2026',  'status' => 'Submitted'],
    ];

    $statusStyle = [
        'Submitted' => 'bg-[#EDE3F5] text-[#5C0F8B]',
        'Amended'   => 'bg-[#FFF1D6] text-[#7A4A00]',
    ];
@endphp

<x-filament-panels::page class="hs-officer-page">

    {{-- Dark header strip (matches police home header style on mobile) --}}
    <div class="bg-[#231A33] px-5 py-5 md:hidden">
        <input
            type="search"
            placeholder="Search by case number"
            class="w-full rounded-lg border-2 border-[#4A3E5E] bg-[#2E2245] px-4 py-3 text-sm text-white placeholder-[#8B7FA8]
                   focus:border-[#5C0F8B] focus:outline-none"
            disabled
        />
    </div>

    <div class="mx-auto flex w-full max-w-250 flex-col gap-6 px-5 py-8 md:px-14 md:py-10">

        {{-- Page header --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="font-montserrat text-2xl font-bold md:text-[32px]">My Assessments</h1>
            </div>
            {{-- Desktop search --}}
            <input
                type="search"
                placeholder="Search by case number"
                class="hidden md:block w-[380px] rounded-lg border-2 border-gray-300 px-4 py-2.5 text-sm text-gray-900
                       focus:border-[#5C0F8B] focus:outline-none"
                disabled
            />
        </div>

        {{-- Filter chips + session notice --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex gap-2">
                @foreach (['All', 'Submitted', 'Amended'] as $i => $label)
                    <button
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-semibold transition
                               {{ $i === 0
                                    ? 'bg-[#5C0F8B] text-white'
                                    : 'border border-[#C9C1D6] bg-white text-[#5A5566] hover:bg-[#F4F2F7]' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            @include('filament.partials.session-notice', ['minutes' => $minutes])
        </div>

        {{-- Desktop table header --}}
        <div class="hidden md:grid md:grid-cols-[2fr_2fr_1.5fr_auto] gap-x-6 px-4 text-xs font-bold uppercase tracking-wider text-[#5A5566]">
            <span>Case Number</span>
            <span>Date Submitted</span>
            <span>Status</span>
            <span></span>
        </div>

        {{-- Rows --}}
        <div class="flex flex-col gap-3">
            @foreach ($rows as $row)

                {{-- Mobile card --}}
                <div class="flex flex-col gap-4 rounded-2xl border border-[#DDD7E6] bg-white p-5 md:hidden">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-montserrat text-[15px] font-bold text-[#1B1726]">{{ $row['case'] }}</p>
                            <p class="mt-0.5 text-sm text-[#5A5566]">{{ $row['date'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $statusStyle[$row['status']] }}">
                            {{ $row['status'] }}
                        </span>
                    </div>
                    <div class="flex gap-2.5">
                        <button
                            type="button"
                            class="flex h-11 flex-1 items-center justify-center rounded-[10px] border border-[#C9C1D6]
                                   text-[15px] font-semibold text-[#1B1726] transition hover:bg-[#F4F2F7]"
                        >
                            View
                        </button>
                        <button
                            type="button"
                            class="flex h-11 flex-1 items-center justify-center rounded-[10px] bg-[#231A33]
                                   text-[15px] font-semibold text-white transition hover:bg-[#180F26]"
                        >
                            Add Amendment
                        </button>
                    </div>
                </div>

                {{-- Desktop row --}}
                <div class="hidden md:grid md:grid-cols-[2fr_2fr_1.5fr_auto] gap-x-6 items-center rounded-2xl border border-[#DDD7E6] bg-white px-4 py-4">
                    <span class="font-montserrat text-[15px] font-bold text-[#1B1726]">{{ $row['case'] }}</span>
                    <span class="text-[15px] text-[#5A5566]">{{ $row['date'] }}</span>
                    <span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusStyle[$row['status']] }}">
                            {{ $row['status'] }}
                        </span>
                    </span>
                    <div class="flex gap-2.5">
                        <button
                            type="button"
                            class="flex h-10 items-center justify-center rounded-[10px] border border-[#C9C1D6]
                                   px-4 text-[14px] font-semibold text-[#1B1726] transition hover:bg-[#F4F2F7]"
                        >
                            View
                        </button>
                        <button
                            type="button"
                            class="flex h-10 items-center justify-center rounded-[10px] bg-[#231A33]
                                   px-4 text-[14px] font-semibold text-white transition hover:bg-[#180F26]"
                        >
                            Add Amendment
                        </button>
                    </div>
                </div>

            @endforeach
        </div>

    </div>
</x-filament-panels::page>
