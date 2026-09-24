<?php

namespace App\Models;

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
