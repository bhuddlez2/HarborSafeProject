<?php

use App\Support\ListNames;

/*
How names on the staff-managed lists are tidied and compared (Burchfield
B55/B60, App\Support\ListNames).
*/

test('spaces are tidied: ends trimmed, runs collapsed', function () {
    expect(ListNames::tidy("  Crisis   Counseling \t"))->toBe('Crisis Counseling')
        ->and(ListNames::tidy(null))->toBeNull();
});

test('misspellings of an existing name are caught', function (string $existing, string $typed) {
    expect(ListNames::clashWith($typed, collect([$existing])))->toBe($existing);
})->with([
    ['Counseling', 'Counselling'],
    ['Bradley', 'Bradly'],
    ['Crisis Counseling', 'Crisis Counceling'],
    ['Court Advocacy', 'Court Advocasy'],
    ['Fundraiser', 'Fundraisor'],
    ['Support Groups', 'Suport Groups'],
    ['Emergency Shelter', 'emergency  shelter'],
    ['Other', 'Othar'],
    // Plurals count as the same name.
    ['Other', 'Others'],
    ['Support Group', 'Support Groups'],
    ['Fundraiser', 'Fundraisers'],
    ['Sheriff Office', "Sheriff's Office"],
]);

test('different names on the real lists are not confused', function (string $existing, string $typed) {
    expect(ListNames::clashWith($typed, collect([$existing])))->toBeNull();
})->with([
    // A whole-name Soundex would call these the same ("C622").
    ['Crisis Counseling', '24/7 Crisis Line'],
    ['Emergency Shelter', 'Emergency Shelter Intake'],
    ['Bradley', 'Polk'],
    ['Hamilton', 'Hamblen'],
    ['Monroe', 'Marion'],
    ['Local Test PD', 'Second Test PD'],
    ['Agency A', 'Agency B'],
]);

test('words with digits must match exactly', function () {
    expect(ListNames::clashWith('District 9', collect(['District 8'])))->toBeNull()
        ->and(ListNames::clashWith('24/7 Crisis Line', collect(['24/7 Crisis Line'])))->toBe('24/7 Crisis Line');
});
