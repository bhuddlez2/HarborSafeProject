<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

//imports other models to reference for foreign keys
use App\Models\SubmitterInfo;

class PrivateAssessment extends BaseModel
{
    //automatically create the UUID for the form
    use EncryptsSensitiveFields, HasUuids;

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
        'OffenderVictimRelationshipOther',
        'VictimFirstName',
        'VictimLastName',
        'VictimSex',
        'VictimDOB',
        'VictimSafePhoneNumber',
        'SubmissionID',
        'AssessmentDocID'        
    ];

    // Every victim and offender detail, encrypted under DATA_ENCRYPTION_KEY
    // (App\Support\SensitiveData) - the same columns as the officer
    // assessment. Searching these goes through App\Support\EncryptedSearch.
    public const ENCRYPTED = LawEnforcementAssessment::PERSON_FIELDS;

    // Stored as (xxx)xxx-xxxx whichever form it arrives in - see
    // App\Support\PhoneNumber, which also validates it.
    protected function phoneNumberFields(): array
    {
        return ['VictimSafePhoneNumber'];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function submitterInfo()
    {
        return $this->belongsTo(SubmitterInfo::class, 'SubmissionID');
    }

    //Make connection to assessment table here
    public function assessmentAnswers()
    {
        return $this->belongsTo(AssessmentAnswers::class, 'AssessmentDocID');
    }

    // Who may see civilian assessments: active admins and super admins, all of them; nobody
    // else, any of them (Filament_CMS_Design.md section 5). The Civilian
    // assessments screen and the PDF download both go through this, as the
    // law-enforcement side goes through LawEnforcementAssessment::visibleTo().
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $user?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin)
            ? $query
            : $query->whereRaw('1 = 0');
    }
}
