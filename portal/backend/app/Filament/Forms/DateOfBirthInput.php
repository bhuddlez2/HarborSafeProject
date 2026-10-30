<?php

namespace App\Filament\Forms;

use App\Support\DateOfBirth;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Support\Htmlable;

/*
Every date of birth box in the panel: the officer wizard and the officer
Edit pop-up. Typed as MM/DD/YYYY with the number keypad, the slashes added
as it's typed (Burchfield B28, Finishing Touches 6) - quicker on a phone
than scrolling a date picker back decades. It replaced a native date picker.

The box holds the typed text: a saved Y-m-d date is shown typed when the form
is filled (the Edit pop-up, a resumed draft), checked against
App\Support\DateOfBirth's range in that format, and handed back as Y-m-d, so
the database, AssessmentEditor and the change log never see the typed form.

Being a text box, an optional one gets the grey "Optional" ghost text from
RequirementMarkers like any other, so it needs no label-side marker.
*/
final class DateOfBirthInput
{
    public static function make(string $name, string | Htmlable $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->mask(DateOfBirth::MASK)
            ->inputMode('numeric')
            ->maxLength(10)
            ->autocomplete(false)
            ->helperText('MM/DD/YYYY')
            ->formatStateUsing(fn (?string $state): ?string => DateOfBirth::toTyped($state))
            ->dehydrateStateUsing(fn (?string $state): ?string => DateOfBirth::toIso($state))
            // Closure, so "today" is worked out on each request.
            ->rules(fn (): array => DateOfBirth::typedRules())
            ->validationMessages([
                'required' => 'Required',
                'date_format' => DateOfBirth::TYPED_MESSAGE,
                'after_or_equal' => DateOfBirth::TOO_OLD_MESSAGE,
                'before_or_equal' => DateOfBirth::FUTURE_MESSAGE,
            ]);
    }
}
