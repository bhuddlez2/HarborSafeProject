<?php

use App\Support\HighRisk;

/*
The one high-risk rule (App\Support\HighRisk), approved by Harbor Safe on
2026-10-25: a yes to any of Q1-3, or 4 or more yes in all. Analytics counts it
and the assessment tables, record view, PDF and officer review colour by it.
*/

// Question numbers answered yes => the eleven answers, keyed 1-11.
function answersWithYes(array $yes): array
{
    return collect(range(1, 11))
        ->mapWithKeys(fn (int $id): array => [$id => in_array($id, $yes, true)])
        ->all();
}

test('a yes to any of the first three questions is high risk on its own', function (int $question) {
    expect(HighRisk::fromAnswers(answersWithYes([$question])))->toBeTrue();
})->with([1, 2, 3]);

test('four or more yes answers are high risk', function () {
    expect(HighRisk::fromAnswers(answersWithYes([4, 5, 6, 7])))->toBeTrue()
        ->and(HighRisk::fromAnswers(answersWithYes(range(4, 11))))->toBeTrue();
});

test('fewer than four yes answers, none of them Q1-3, are not high risk', function () {
    expect(HighRisk::fromAnswers(answersWithYes([4, 5, 6])))->toBeFalse()
        ->and(HighRisk::fromAnswers(answersWithYes([11])))->toBeFalse()
        ->and(HighRisk::fromAnswers(answersWithYes([])))->toBeFalse();
});

test('stored answers (1, 0 and unanswered) are read the same way', function () {
    expect(HighRisk::fromAnswers([1 => 0, 2 => 1, 3 => null]))->toBeTrue()
        ->and(HighRisk::fromAnswers([1 => 0, 2 => null, 4 => 1, 5 => 1, 6 => 1]))->toBeFalse()
        ->and(HighRisk::of(null))->toBeFalse();
});
