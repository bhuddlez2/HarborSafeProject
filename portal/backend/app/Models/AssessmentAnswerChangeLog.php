<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentAnswerChangeLog extends BaseModel
{
    use AppendOnly, HasUuids;

    protected $connection = 'Portal';

    protected $table = 'assessment_answer_change_log';

    protected $primaryKey = 'LogID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'EditID',
        'AssessmentDocID',
        'ChangeField',
        'PreviousValue',
        'NewValue',
        'ChangedBy',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function assessmentAnswers()
    {
        return $this->belongsTo(AssessmentAnswers::class, 'AssessmentDocID');
    }

    // The save this change belongs to: who, when and why.
    public function edit()
    {
        return $this->belongsTo(AssessmentEdit::class, 'EditID');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'ChangedBy');
    }
}
