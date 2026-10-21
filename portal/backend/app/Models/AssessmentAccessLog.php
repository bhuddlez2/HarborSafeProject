<?php

namespace App\Models;

use App\Enums\AssessmentAccess;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use InvalidArgumentException;

// One view or PDF download of a form that shows a safe phone number - an
// officer or civilian assessment, or a resource request: who, what and when.
// Written through record(), never changed afterwards.
class AssessmentAccessLog extends BaseModel
{
    use AppendOnly, HasUuids;

    protected $connection = 'Portal';

    protected $table = 'assessment_access_log';

    protected $primaryKey = 'AccessID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'LawEnforcementDocumentID',
        'PrivateDocumentID',
        'ResourceRequestFormID',
        'Action',
        'AccessedBy',
    ];

    protected function casts(): array
    {
        return [
            'Action' => AssessmentAccess::class,
            'AccessedAt' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    // The one way rows are written. AccessedAt is the database's own time
    // (UTC, see App\Support\Timezones).
    // A resource request counts too (since 2026-10-20): its follow-up page and
    // PDF show the requester's safe phone number.
    public static function record(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm $assessment, AssessmentAccess $action, User $user): self
    {
        $column = match (true) {
            $assessment instanceof LawEnforcementAssessment => 'LawEnforcementDocumentID',
            $assessment instanceof PrivateAssessment => 'PrivateDocumentID',
            $assessment instanceof ResourceRequestForm => 'ResourceRequestFormID',
            default => throw new InvalidArgumentException('Not a form the log records.'),
        };

        return static::create([
            $column => $assessment->getKey(),
            'Action' => $action,
            'AccessedBy' => $user->getKey(),
        ]);
    }

    public function lawEnforcementAssessment()
    {
        return $this->belongsTo(LawEnforcementAssessment::class, 'LawEnforcementDocumentID');
    }

    public function privateAssessment()
    {
        return $this->belongsTo(PrivateAssessment::class, 'PrivateDocumentID');
    }

    // On the Feedback connection, so no foreign key: the form can be gone
    // (a resource request deleted as spam) while its log rows remain.
    public function resourceRequestForm()
    {
        return $this->belongsTo(ResourceRequestForm::class, 'ResourceRequestFormID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'AccessedBy');
    }
}
