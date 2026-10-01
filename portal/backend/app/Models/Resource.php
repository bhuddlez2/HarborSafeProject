<?php

namespace App\Models;

class Resource extends BaseModel
{
    protected $connection = 'Feedback';

    protected $table = 'resources';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'ChangeDate',
    ];

    // Inverse of ResourceRequestForm::resourceTypes(), through the pivot that
    // replaced the old singular ResourceTypeID FK (migration 2026_09_15_090100).
    // Used by the panel to decide whether a resource type can be deleted.
    public function requests()
    {
        return $this->belongsToMany(
            ResourceRequestForm::class,
            'resource_request_resource_types',
            'ResourceTypeID',
            'FormID'
        );
    }
}
