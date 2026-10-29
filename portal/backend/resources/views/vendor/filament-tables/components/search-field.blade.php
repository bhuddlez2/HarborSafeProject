@props([
    'debounce' => '500ms',
    'label' => __('filament-tables::table.fields.search.label'),
    'labelHidden' => true,
    'onBlur' => false,
    'placeholder' => __('filament-tables::table.fields.search.placeholder'),
    'wireModel' => 'tableSearch',
])

{{--
    Filament's own search box (vendor/filament/tables/resources/views/components/search-field.blade.php),
    copied unchanged except for its maxlength: 1000 there, App\Filament\Tables\TableSearch::MAX_LENGTH
    here. After a Filament upgrade, compare it with the vendor copy and carry over any changes.
--}}
@php
    use App\Filament\Tables\TableSearch;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Tables\View\TablesIconAlias;

    $wireModelAttribute = $onBlur ? 'wire:model.live.blur' : "wire:model.live.debounce.{$debounce}";
@endphp

<div
    x-id="['input']"
    {{ $attributes->class(['fi-ta-search-field']) }}
>
    <label
        x-bind:for="$id('input')"
        @class([
            'fi-sr-only' => $labelHidden,
            'fi-ta-cell-label' => ! $labelHidden,
        ])
    >
        {{ $label }}
    </label>

    <x-filament::input.wrapper
        inline-prefix
        :prefix-icon="Heroicon::MagnifyingGlass"
        :prefix-icon-alias="TablesIconAlias::SEARCH_FIELD"
        :wire:target="$wireModel"
    >
        <x-filament::input
            :attributes="
                (new FilamentComponentAttributeBag)->merge([
                    'autocomplete' => 'off',
                    'inlinePrefix' => true,
                    'maxlength' => TableSearch::MAX_LENGTH,
                    'placeholder' => $placeholder,
                    'type' => 'search',
                    'wire:key' => $this->getId() . '.table.' . $wireModel . '.field.input',
                    $wireModelAttribute => $wireModel,
                    'x-bind:id' => '$id(\'input\')',
                    'x-on:keyup' => 'if ($event.key === \'Enter\') { $wire.$refresh() }',
                ], escape: false)
            "
        />
    </x-filament::input.wrapper>
</div>
