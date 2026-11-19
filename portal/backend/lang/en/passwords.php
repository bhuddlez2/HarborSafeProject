<?php

// Laravel's wording, replaced where it names a "token" or "user": shown on the
// reset and set-up pages (App\Filament\Pages\Auth) when a link can't be used.
// An inactive account gets the same message as a used or expired link.
// Laravel's other password messages still apply.
return [
    'token' => 'This link has expired or has already been used. Please ask for a new one.',
    'user' => 'This link has expired or has already been used. Please ask for a new one.',
];
