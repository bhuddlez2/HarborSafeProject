<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class PrivateAssessment extends BaseModel
{
    //automatically create the UUID for the form
    use HasUuids;

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = 'private_assessments';

    //UUID specifications
    protected $keyType = 'string';
    public $incrementing = false;

    //Fillable Columns
    protected $fillable = [
        'SubmitterEmail',
        'SubmitterPhoneNumber',
        'SubmitterFirstName',
        'SubmitterLastName',
        'OffenderFirstName',
        'OffenderLastName',
        'OffenderSex',
        'OffenderDOB',
        'OffenderVictimRelationship',
        'VictimFirstName',
        'VictimLastName',
        'VictimSex',
        'VictimDOB',
        'VictimSafePhoneNumber'
    ];
}
