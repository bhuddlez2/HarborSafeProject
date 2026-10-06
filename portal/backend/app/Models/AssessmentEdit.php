<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Support\AssessmentFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

// One save of a law-enforcement assessment: who, when and why. Its field-level
// changes are the detailChanges and answerChanges rows. Written only by
// App\Services\AssessmentEditor, and never changed afterwards.
class AssessmentEdit extends BaseModel
{
    use AppendOnly, HasUuids;

    protected $connection = 'Portal';

    protected $table = 'assessment_edits';

    protected $primaryKey = 'EditID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'DocumentID',
        'ChangedBy',
        'Reason',
        'EditedAt',
    ];

    protected function casts(): array
    {
        return [
            'EditedAt' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function assessment()
    {
        return $this->belongsTo(LawEnforcementAssessment::class, 'DocumentID');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'ChangedBy');
    }

    public function detailChanges()
    {
        return $this->hasMany(AssessmentChangeLog::class, 'EditID');
    }

    public function answerChanges()
    {
        return $this->hasMany(AssessmentAnswerChangeLog::class, 'EditID');
    }

    /*
    Detail and answer changes together, readable, in a fixed order: details as
    the edit form lays them out, then the questions by number.

    @return list<array{label: string, from: string, to: string}>
    */
    public function changes(): array
    {
        $detailOrder = array_flip(array_keys(AssessmentFields::DETAIL_LABELS));

        $details = $this->detailChanges
            ->sortBy(fn (AssessmentChangeLog $change): int => $detailOrder[$change->ChangeField] ?? PHP_INT_MAX);

        $answers = $this->answerChanges
            ->sortBy(fn (AssessmentAnswerChangeLog $change): int => (int) str_replace('RiskIndicator', '', $change->ChangeField));

        return $details->concat($answers)
            ->map(fn ($change): array => AssessmentFields::describe($change->ChangeField, $change->PreviousValue, $change->NewValue))
            ->values()
            ->all();
    }
}
