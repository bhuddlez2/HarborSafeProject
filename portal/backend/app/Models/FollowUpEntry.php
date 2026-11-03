<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

// One step in a form's follow-up history: a status change with its note, or a
// note on its own. Written only by App\Services\FollowUps, never changed.
class FollowUpEntry extends BaseModel
{
    use AppendOnly, EncryptsSensitiveFields, HasUuids;

    // Staff write about the person they reached, so the note is encrypted
    // under DATA_ENCRYPTION_KEY (App\Support\SensitiveData).
    public const ENCRYPTED = ['Note'];

    protected $connection = 'Portal';

    protected $table = 'follow_up_entries';

    protected $primaryKey = 'EntryID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'FollowUpID',
        'FromStatus',
        'Status',
        'Note',
        'EnteredBy',
        'EnteredAt',
    ];

    protected function casts(): array
    {
        return [
            'FromStatus' => FollowUpStatus::class,
            'Status' => FollowUpStatus::class,
            'EnteredAt' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function followUp()
    {
        return $this->belongsTo(FollowUp::class, 'FollowUpID');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'EnteredBy');
    }

    public function changedStatus(): bool
    {
        return $this->FromStatus !== null;
    }
}
