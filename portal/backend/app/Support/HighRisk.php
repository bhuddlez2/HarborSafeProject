<?php

namespace App\Support;

use App\Models\AssessmentAnswers;

/*
Which assessments count as high risk, decided once for every screen: the
Analytics totals (AssessmentStats), the red Yes badge on the assessment tables,
the record view and its PDF, the officer wizard's review, and the civilian
table's high-risk filter.

The rule: a yes to any of Q1-3 (weapon, threat to kill, choking), or 4 or more
yes in all. Approved by Harbor Safe on 2026-10-25.
*/
final class HighRisk
{
    public const RULE = 'Yes to Q1–3, or 4 or more yes';

    // A yes to any one of these is high risk on its own.
    private const DECISIVE_QUESTIONS = [1, 2, 3];

    private const YES_THRESHOLD = 4;

    private const QUESTION_COUNT = 11;

    /**
     * @param  array<int, mixed>  $answers  question number => the answer (truthy is yes)
     */
    public static function fromAnswers(array $answers): bool
    {
        foreach (self::DECISIVE_QUESTIONS as $id) {
            if ((bool) ($answers[$id] ?? false)) {
                return true;
            }
        }

        return count(array_filter($answers)) >= self::YES_THRESHOLD;
    }

    public static function of(?AssessmentAnswers $answers): bool
    {
        if (! $answers) {
            return false;
        }

        return self::fromAnswers(collect(range(1, self::QUESTION_COUNT))
            ->mapWithKeys(fn (int $id): array => [$id => $answers->{"RiskIndicator{$id}"}])
            ->all());
    }

    // The rule as SQL on _assessment_answers' columns; pass the table's alias
    // when the query has one (`x` gives x.RiskIndicator1 ...).
    public static function sql(?string $alias = null): string
    {
        $column = fn (int $id): string => ($alias ? "{$alias}." : '')."RiskIndicator{$id}";

        $decisive = collect(self::DECISIVE_QUESTIONS)
            ->map(fn (int $id): string => $column($id).' = 1')
            ->implode(' OR ');

        $yes = collect(range(1, self::QUESTION_COUNT))
            ->map(fn (int $id): string => 'COALESCE('.$column($id).', 0)')
            ->implode(' + ');

        return "({$decisive} OR ({$yes}) >= ".self::YES_THRESHOLD.')';
    }
}
