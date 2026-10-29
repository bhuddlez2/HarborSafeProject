<?php

namespace App\Filament\Forms;

use App\Support\DateOfBirth;
use Filament\Forms\Components\DatePicker;
use Illuminate\Contracts\Support\Htmlable;

/*
Every date of birth box in the panel: the officer wizard and the officer
Edit pop-up. The calendar only offers days within App\Support\DateOfBirth's
range, and the same range is checked on save.
*/
final class DateOfBirthPicker
{
    public static function make(string $name, string | Htmlable $label): DatePicker
    {
        return DatePicker::make($name)
            ->label($label)
            ->native()
            // Closures, so "today" is worked out on each request.
            ->minDate(fn (): string => DateOfBirth::earliest())
            ->maxDate(fn (): string => DateOfBirth::latest())
            ->validationMessages([
                'required' => 'Required',
                'date' => 'Enter a valid date',
                'after_or_equal' => DateOfBirth::TOO_OLD_MESSAGE,
                'before_or_equal' => DateOfBirth::FUTURE_MESSAGE,
            ]);
    }
}
