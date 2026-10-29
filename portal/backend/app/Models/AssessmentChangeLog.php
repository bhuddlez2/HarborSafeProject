<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentChangeLog extends BaseModel
{
    use AppendOnly, EncryptsSensitiveFields, HasUuids;

    // The old and new values of an edited detail are copies of the
    // assessment's own encrypted columns, so they are encrypted too
    // (App\Support\SensitiveData) - plain copies would undo it.
    public const ENCRYPTED = ['PreviousValue', 'NewValue'];

    protected $connection = 'Portal';

    protected $table = 'assessment_change_log';

    protected $primaryKey = 'ChangeLogID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'EditID',
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
