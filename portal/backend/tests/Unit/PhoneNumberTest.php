<?php

use App\Support\PhoneNumber;

/*
The one phone-number rule (App\Support\PhoneNumber): a full 10-digit number,
area code included, stored as (xxx)xxx-xxxx. The website's
lib/validators.js follows the same rule.
*/

test('a full 10-digit number is accepted and stored as (xxx)xxx-xxxx, whatever its punctuation', function (string $typed) {
    expect(PhoneNumber::digits($typed))->toBe('4235550100')
        ->and(PhoneNumber::format($typed))->toBe('(423)555-0100');
})->with([
    'digits only' => '4235550100',
    'dashes' => '423-555-0100',
    'brackets and space' => '(423) 555-0100',
    'already formatted' => '(423)555-0100',
    'dots' => '423.555.0100',
    'country code' => '+1 423 555 0100',
    'country code with dashes' => '1-423-555-0100',
]);

test('anything short of a full number with an area code is refused, and kept as typed', function (string $typed) {
    expect(PhoneNumber::digits($typed))->toBeNull()
        ->and(PhoneNumber::format($typed))->toBe($typed);
})->with([
    'seven digits, no area code' => '555-0100',
    'nine digits' => '423-555-010',
    'eleven digits not starting with 1' => '24235550100',
    'area code starting with 1' => '123-555-0100',
    'area code starting with 0' => '023-555-0100',
    'letters' => 'call me',
]);
