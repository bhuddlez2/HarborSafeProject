<?php

namespace App\Support;

use App\Rules\ValidPhoneNumber;

/*
Phone numbers, one rule for every form that takes one: the victim's safe
number on both assessments, the civilian submitter's number, and the
requester's number on resource requests.

A valid number is a full 10-digit North American number - area code
included, so Harbor Safe can always call back. Punctuation and spaces are
ignored, and so is a leading country code 1. An area code can't start with 0
or 1, which also catches a stray leading 1 typed as part of the number.

Stored as (xxx)xxx-xxxx: the models format on save (see each model's phone
attribute), so the database, the PDFs and any export read the same way. The
website's validators.js and both apps' input masks follow the same rule.
*/
final class PhoneNumber
{
    public const MESSAGE = 'Enter a 10-digit phone number, starting with the area code.';

    // For Filament's ->mask(): formats the box as the user types.
    public const MASK = '(999)999-9999';

    // The ten digits, or null when the value isn't a valid number.
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[2-9]\d{9}$/', $digits) ? $digits : null;
    }

    // (xxx)xxx-xxxx for a valid number; anything else is returned unchanged,
    // so a value that somehow got past validation is never lost or altered.
    public static function format(?string $value): ?string
    {
        $digits = self::digits($value);

        return $digits === null
            ? $value
            : sprintf('(%s)%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }

    // Validation rule for Laravel and Filament: ->rule(PhoneNumber::rule()).
    public static function rule(): ValidPhoneNumber
    {
        return new ValidPhoneNumber;
    }
}
