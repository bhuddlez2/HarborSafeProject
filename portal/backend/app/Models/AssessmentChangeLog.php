<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentChangeLog extends BaseModel
{
    use HasUuids;

    protected $connection = 'Portal';

    protected $table = 'assessment_change_log';

    protected $primaryKey = 'ChangeLogID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'DocumentID',
        'ChangeField',
        'PreviousValue',
        'NewValue',
        'ChangedBy',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function assessment()
    {
        return $this->belongsTo(LawEnforcementAssessment::class, 'DocumentID');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'ChangedBy');
    }
}
