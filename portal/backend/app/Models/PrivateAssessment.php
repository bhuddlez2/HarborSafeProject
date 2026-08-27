<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

//imports other models to reference for foreign keys
use App\Models\SubmitterInfo;

class PrivateAssessment extends BaseModel
{
    //automatically create the UUID for the form
    use HasUuids;

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = '_private_assessment';

    //UUID specifications
    protected $primaryKey = "DocumentID";
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;


    //Fillable Columns
    protected $fillable = [
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
        'SubmissionID',
        'AssessmentDocID'        
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function SubmitterInfo()
    {
        return $this->belongsTo(SubmitterInfo::class, 'SubmissionID');
    }

    //Make connection to assessment table here
    public function AssessmentAnswers()
    {
        return $this->belongsTo(AssessmentAnswers::class, 'AssessmentDocID');
    }
}
