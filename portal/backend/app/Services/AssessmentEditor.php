<?php

namespace App\Services;

use App\Models\AssessmentAnswerChangeLog;
use App\Models\AssessmentChangeLog;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use App\Support\AssessmentFields;
use Illuminate\Support\Facades\DB;

/*
The only way a law-enforcement assessment, or its risk answers, may change.

Every save becomes one assessment_edits row (who, when, why) plus one
field-level row per changed column, all in one Portal transaction - so an edit
and its log either both land or neither does. A save that changes nothing
writes nothing and returns null.

Both models refuse any other update (EditedOnlyThroughAssessmentEditor), and
the log rows are append-only, so the history cannot be skipped or rewritten.

Who may edit is not decided here: callers check
LawEnforcementAssessmentPolicy::update() first (owning officer only).
*/
final class AssessmentEditor
{
    private static bool $applying = false;

    public static function isApplying(): bool
    {
        return self::$applying;
    }

    /**
     * @param  array<string, mixed>  $details  law_enforcement_assessment columns
     * @param  array<string, bool>  $answers  RiskIndicator1..11
     */
    public static function apply(
        LawEnforcementAssessment $record,
        array $details,
        array $answers,
        string $reason,
        User $editor,
    ): ?AssessmentEdit {
        // Compare against what is stored now, not whatever this instance last
        // saw: a stale or freshly created model would log phantom changes.
        $record->refresh();
        $answerRow = $record->assessmentAnswers;

        // Only the columns this editor knows about, whatever the caller passed.
        $record->fill(array_intersect_key($details, AssessmentFields::DETAIL_LABELS));
        $answerRow->fill(array_intersect_key($answers, array_flip(self::answerFields())));

        // Normalise so "" and null, or 1 and true, do not register as changes.
        self::discardNonChanges($record);
        self::discardNonChanges($answerRow);

        $detailChanges = $record->getDirty();
        $answerChanges = $answerRow->getDirty();

        if ($detailChanges === [] && $answerChanges === []) {
            return null;
        }

        return DB::connection('Portal')->transaction(function () use (
            $record, $answerRow, $detailChanges, $answerChanges, $reason, $editor,
        ): AssessmentEdit {
            $edit = AssessmentEdit::create([
                'DocumentID' => $record->getKey(),
                'ChangedBy' => $editor->getKey(),
                'Reason' => $reason,
                'EditedAt' => now(),
            ]);

            foreach ($detailChanges as $field => $new) {
                AssessmentChangeLog::create([
                    'EditID' => $edit->getKey(),
                    'DocumentID' => $record->getKey(),
                    'ChangeField' => $field,
                    'PreviousValue' => $record->getOriginal($field),
                    'NewValue' => $new,
                    'ChangedBy' => $editor->getKey(),
                ]);
            }

            foreach ($answerChanges as $field => $new) {
                AssessmentAnswerChangeLog::create([
                    'EditID' => $edit->getKey(),
                    'AssessmentDocID' => $answerRow->getKey(),
                    'ChangeField' => $field,
                    'PreviousValue' => (bool) $answerRow->getOriginal($field),
                    'NewValue' => (bool) $new,
                    'ChangedBy' => $editor->getKey(),
                ]);
            }

            self::$applying = true;

            try {
                $record->save();
                $answerRow->save();
            } finally {
                self::$applying = false;
            }

            return $edit;
        });
    }

    /** @return list<string> */
    public static function answerFields(): array
    {
        return array_map(fn (int $n): string => "RiskIndicator{$n}", range(1, 11));
    }

    // Resets any attribute whose new value means the same as the original -
    // a blank field that was null, a 1 that was true - so it is not logged.
    private static function discardNonChanges(\Illuminate\Database\Eloquent\Model $model): void
    {
        foreach ($model->getDirty() as $field => $new) {
            $old = $model->getOriginal($field);

            $same = str_starts_with($field, 'RiskIndicator')
                ? (bool) $old === (bool) $new
                : (string) ($old ?? '') === (string) ($new ?? '');

            if ($same) {
                $model->setAttribute($field, $old);
            }
        }
    }
}
