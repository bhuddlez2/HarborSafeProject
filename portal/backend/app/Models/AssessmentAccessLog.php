<?php

namespace App\Models;

use App\Enums\AssessmentAccess;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use InvalidArgumentException;

// One view or PDF download of an assessment, law-enforcement or civilian: who,
// what and when. Written through record(), never changed afterwards.
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
    public static function record(LawEnforcementAssessment | PrivateAssessment $assessment, AssessmentAccess $action, User $user): self
    {
        $column = match (true) {
            $assessment instanceof LawEnforcementAssessment => 'LawEnforcementDocumentID',
            $assessment instanceof PrivateAssessment => 'PrivateDocumentID',
            default => throw new InvalidArgumentException('Not an assessment.'),
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

    public function user()
    {
        return $this->belongsTo(User::class, 'AccessedBy');
    }
}
