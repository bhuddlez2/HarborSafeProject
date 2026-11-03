<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// A new password may use only what's on a standard US keyboard: letters,
// numbers, spaces and the 32 keyboard symbols (printable ASCII). Part of the
// one password rule, Password::defaults() in AppServiceProvider.
//
// No character is dangerous in itself: a password is never put into a
// database query or onto a page, only hashed. This rule exists because:
//   - bcrypt reads only the first 72 bytes. 64 keyboard characters are 64
//     bytes, but an accented letter is 2 bytes and an emoji 4, so a long
//     password with them would have its end silently ignored;
//   - a null character makes bcrypt throw, which showed as a server error;
//   - invisible and look-alike characters (or the same letter encoded two
//     ways by different devices) can lock someone out of their own account.
class KeyboardCharactersOnly implements ValidationRule
{
    public const MESSAGE = 'The password may use only letters, numbers, spaces and the symbols on a standard keyboard.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[\x20-\x7E]*$/D', $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }
}
