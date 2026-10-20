<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ResourceRequestForm extends BaseModel
{
    use HasUuids;

    protected $connection = 'Feedback';

    protected $table = 'resource_request_form';

    protected $primaryKey = 'FormID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'FirstName',
        'LastName',
        'EmailAddress',
        'SafePhoneNumber',
        'CountyID',
        'Message',
    ];

    // Stored as (xxx)xxx-xxxx whichever form it arrives in - see
    // App\Support\PhoneNumber, which also validates it.
    protected function safePhoneNumber(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => PhoneNumber::format($value));
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    // Multi-select "resources of interest" - see resource_request_resource_types
    // (migration 2026_09_15_090100). Replaced the old singular ResourceTypeID FK.
    public function resourceTypes()
    {
        return $this->belongsToMany(
            Resource::class,
            'resource_request_resource_types',
            'FormID',
            'ResourceTypeID'
        );
    }

    public function county()
    {
        return $this->belongsTo(County::class, 'CountyID');
    }
}
