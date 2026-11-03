<?php

use App\Filament\Pages\NewAssessment;

/*
The 11 lethality questions are written out twice: NewAssessment::QUESTIONS
for the officer wizard, and website/frontend/src/app/lib/lethality-questions.js
for the civilian assessment on the website. They must say the same thing in
the same order, so a change made to one and not the other fails here.
*/

test('the officer wizard and the website ask the same 11 questions', function () {
    // A plain path: unit tests don't boot Laravel, so base_path() isn't there.
    $file = dirname(__DIR__, 4).'/website/frontend/src/app/lib/lethality-questions.js';

    if (! is_file($file)) {
        $this->markTestSkipped('The website is not checked out next to the backend.');
    }

    preg_match_all('/\{\s*id:\s*(\d+),\s*text:\s*"((?:[^"\\\\]|\\\\.)*)"\s*\}/', file_get_contents($file), $matches, PREG_SET_ORDER);

    $website = collect($matches)
        ->mapWithKeys(fn (array $match): array => [(int) $match[1] => stripcslashes($match[2])])
        ->all();

    expect($website)->toBe(NewAssessment::QUESTIONS);
});
