<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// A full 10-digit phone number, area code included - see App\Support\PhoneNumber.
// A class rather than a closure, because Filament treats a closure handed to
// ->rule() as something to call for a rule, not as the rule itself.
class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::digits($value) === null) {
            $fail(PhoneNumber::MESSAGE);
        }
    }
}
