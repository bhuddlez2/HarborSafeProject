{{-- Above the charts: the date range, and on the assessment tabs the Filters
     button: victim sex, offender sex, relationship and (Law-Enforcement only)
     agency, each optional. See App\Filament\Analytics\AnalyticsPage. --}}
@php
    $filterLabel = $this->filterLabel();
    $filterFields = [
        ['draftVictimSex', 'analytics-victim-sex', 'Victim sex', $this->sexOptions()],
        ['draftOffenderSex', 'analytics-offender-sex', 'Offender sex', $this->sexOptions()],
        ['draftRelationship', 'analytics-relationship', 'Relationship', $this->relationshipOptions()],
    ];
    if ($this->offersAgencyFilter()) {
        $filterFields[] = ['draftAgency', 'analytics-agency', 'Agency', $this->agencyOptions()];
    }
    $pill = 'min-h-11 rounded-full border px-3.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#5C0F8B]/50';
@endphp

<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-3">
        <span id="analytics-range-label" class="text-sm font-bold text-gray-700">Date range</span>

        <div role="group" aria-labelledby="analytics-range-label" class="flex flex-wrap gap-1.5">
            @foreach ($this->rangeOptions() as $key => $label)
                <button
                    type="button"
                    wire:click="setRange('{{ $key }}')"
                    aria-pressed="{{ $range === $key ? 'true' : 'false' }}"
                    @class([
                        $pill,
                        'border-[#5C0F8B] bg-[#5C0F8B] font-bold text-white' => $range === $key,
                        'border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-50' => $range !== $key,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>

        @if ($this->offersFilters())
            <div
                class="relative"
                x-data="{ open: false }"
                x-on:analytics-filter-closed.window="open = false"
                x-on:click.outside="open = false"
                x-on:keydown.escape.stop="open = false"
            >
                <button
                    type="button"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open.toString()"
                    aria-controls="analytics-filters"
                    @class([
                        $pill,
                        'relative inline-flex items-center gap-1.5 font-semibold',
                        'border-[#5C0F8B] bg-[#F3E8FA] text-[#5C0F8B]' => $filterLabel,
                        'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => ! $filterLabel,
                    ])
                >
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedFunnel" class="size-5" />
                    Filters
                    @if ($filterLabel)
                        <span class="sr-only">(on)</span>
                        <span class="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-white bg-[#5C0F8B]"></span>
                    @endif
                </button>

                <div
                    id="analytics-filters"
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    class="absolute left-0 z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-lg"
                >
                    <form wire:submit="applyFilters" class="flex flex-col gap-4">
                        <p class="font-montserrat text-sm font-bold text-gray-900">Filters</p>

                        @foreach ($filterFields as [$model, $id, $label, $options])
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $id }}" class="text-sm font-semibold text-gray-700">{{ $label }}</label>
                                <x-filament::input.wrapper :valid="! $errors->has($model)">
                                    <x-filament::input.select :id="$id" wire:model="{{ $model }}">
                                        <option value="">Any</option>
                                        @foreach ($options as $value => $name)
                                            <option value="{{ $value }}">{{ $name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                @error($model)
                                    <p class="text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        <div class="flex gap-2">
                            <x-filament::button type="submit">Apply</x-filament::button>
                            <x-filament::button type="button" color="gray" wire:click="resetFilters">Reset</x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <span class="text-sm text-gray-500">{{ $rangeLabel }} · Eastern time</span>
    </div>

    @if ($range === \App\Support\Analytics\DateRange::CUSTOM)
        <form wire:submit="applyCustomRange" class="flex flex-wrap items-start gap-3">
            <div class="flex flex-col gap-1">
                <label for="analytics-from" class="text-sm font-semibold text-gray-700">From<sup class="fi-fo-field-label-required-mark font-medium text-danger-600">*</sup></label>
                <x-filament::input.wrapper :valid="! $errors->has('customFrom')">
                    <x-filament::input type="date" id="analytics-from" wire:model="customFrom" :max="\App\Support\Timezones::today()" required />
                </x-filament::input.wrapper>
                @error('customFrom')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col gap-1">
                <label for="analytics-to" class="text-sm font-semibold text-gray-700">To<sup class="fi-fo-field-label-required-mark font-medium text-danger-600">*</sup></label>
                <x-filament::input.wrapper :valid="! $errors->has('customTo')">
                    <x-filament::input type="date" id="analytics-to" wire:model="customTo" :max="\App\Support\Timezones::today()" required />
                </x-filament::input.wrapper>
                @error('customTo')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="basis-full">@include('filament.partials.required-note')</div>
            <x-filament::button type="submit" class="-mt-1">Apply</x-filament::button>
        </form>
    @endif

    @if ($filterLabel)
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex min-h-8 items-center rounded-full bg-[#F3E8FA] px-3 text-sm font-semibold text-[#5C0F8B]">{{ $filterLabel }}</span>
            <button type="button" wire:click="resetFilters" class="min-h-8 rounded px-1 text-sm font-semibold text-[#5C0F8B] underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#5C0F8B]/50">Clear filter</button>
        </div>
    @endif
</div>
