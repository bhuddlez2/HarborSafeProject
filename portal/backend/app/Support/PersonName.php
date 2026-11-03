<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/*
People's names, one rule for every typed name: the first letter of each word,
and of each part after a hyphen, is made a capital. Every other letter is left
exactly as typed, so "McDonald", "DeShawn" and "JANE" are never changed:
"mary-jane o'brien" becomes "Mary-Jane O'brien".

Applied on save (EncryptsSensitiveFields::nameFields() for the forms, User's
saving hook for staff accounts) and in the boxes as you leave them: the panel
through tidyOnBlur(), the website through capitalizeName() in
lib/validators.js, which mirrors it.
*/
final class PersonName
{
    public static function capitalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace_callback(
            '/(^|[\s-])(\p{Ll})/u',
            fn (array $match): string => $match[1].mb_strtoupper($match[2]),
            $value,
        ) ?? $value;
    }

    // For a panel name box: tidies it when the person leaves the box.
    public static function tidyOnBlur(TextInput $input): TextInput
    {
        return $input
            ->live(onBlur: true)
            ->afterStateUpdated(fn (TextInput $component, ?string $state) => $component->state(self::capitalize($state)));
    }
}
