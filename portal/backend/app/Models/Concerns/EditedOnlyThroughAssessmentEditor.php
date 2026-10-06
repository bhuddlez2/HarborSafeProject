<?php

namespace App\Models\Concerns;

use App\Services\AssessmentEditor;
use LogicException;

// An assessment edit that skips AssessmentEditor would leave no change log,
// so any other update - the API controller, tinker, future code - is refused
// outright rather than quietly going unrecorded. Creating rows is unaffected.
trait EditedOnlyThroughAssessmentEditor
{
    public static function bootEditedOnlyThroughAssessmentEditor(): void
    {
        static::updating(function (): void {
            if (! AssessmentEditor::isApplying()) {
                throw new LogicException(
                    static::class.' may only be changed through App\Services\AssessmentEditor, which records the change log.'
                );
            }
        });
    }
}
