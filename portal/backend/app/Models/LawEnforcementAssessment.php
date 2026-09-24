<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LawEnforcementAssessment extends BaseModel
{
    use HasUuids;

    protected $connection = 'Portal';

    protected $table = 'law_enforcement_assessment';

    protected $primaryKey = 'DocumentID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'submitted_by',
        'OffenderFirstName',
        'OffenderLastName',
        'OffenderSex',
        'OffenderDOB',
        'OffenderVictimRelationship',
        'VictimFirstName',
        'VictimLastName',
        'VictimSex',
        'VictimDOB',
        'VictimSafePhoneNumber',
        'AssessmentDocID',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assessmentAnswers()
    {
        return $this->belongsTo(AssessmentAnswers::class, 'AssessmentDocID');
    }
}
