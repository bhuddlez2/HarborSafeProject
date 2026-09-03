<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentAnswerChangeLog extends BaseModel
{
    use HasUuids;

    protected $connection = 'Portal';

    protected $table = 'assessment_answer_change_log';

    protected $primaryKey = 'LogID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
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

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'ChangedBy');
    }
}
