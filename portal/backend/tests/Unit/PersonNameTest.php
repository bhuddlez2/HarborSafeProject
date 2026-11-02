<?php

use App\Support\PersonName;

// One rule for every typed name: capitalise each word's first letter, leave the rest as typed.
test('each word and each part after a hyphen starts with a capital', function (?string $typed, ?string $saved) {
    expect(PersonName::capitalize($typed))->toBe($saved);
})->with([
    'one word' => ['jane', 'Jane'],
    'two words' => ['mary ann', 'Mary Ann'],
    'a hyphen' => ['smith-jones', 'Smith-Jones'],
    'an accented letter' => ['émilie', 'Émilie'],
    'already right' => ['Jane', 'Jane'],
    'capitals inside are kept' => ['mcDonald', 'McDonald'],
    'all capitals are kept' => ['JANE', 'JANE'],
    'only the first letter after an apostrophe' => ["o'brien", "O'brien"],
    'spaces around are kept' => ['  jane ', '  Jane '],
    'empty' => ['', ''],
    'none' => [null, null],
]);
