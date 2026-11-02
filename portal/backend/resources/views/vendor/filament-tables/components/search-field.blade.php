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
    copied unchanged except for two things. Its maxlength: 1000 there, App\Filament\Tables\TableSearch::MAX_LENGTH
    here. And a table's ->searchPlaceholder() ('Search by Title') shows only while the box has focus,
    plain "Search" otherwise, and is the label screen readers hear (Burchfield B38); wire:ignore.self
    stops a table refresh putting "Search" back while someone is still in the box.
    After a Filament upgrade, compare it with the vendor copy and carry over any changes.
--}}
@php
    use App\Filament\Tables\TableSearch;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Tables\View\TablesIconAlias;
    use Illuminate\Support\Js;

    $wireModelAttribute = $onBlur ? 'wire:model.live.blur' : "wire:model.live.debounce.{$debounce}";

    $idleText = __('filament-tables::table.fields.search.placeholder');
    $focusText = filled($placeholder) && $placeholder !== $idleText ? $placeholder : null;
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
        {{ $focusText ?? $label }}
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
                    'placeholder' => $idleText,
                    'type' => 'search',
                    'wire:key' => $this->getId() . '.table.' . $wireModel . '.field.input',
                    $wireModelAttribute => $wireModel,
                    'x-bind:id' => '$id(\'input\')',
                    'x-on:keyup' => 'if ($event.key === \'Enter\') { $wire.$refresh() }',
                    ...($focusText ? [
                        'wire:ignore.self' => true,
                        'x-on:focus' => '$el.placeholder = ' . Js::from($focusText),
                        'x-on:blur' => '$el.placeholder = ' . Js::from($idleText),
                    ] : []),
                ], escape: false)
            "
        />
    </x-filament::input.wrapper>
</div>
