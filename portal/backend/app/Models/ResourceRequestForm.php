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
        'ResourceTypeID',
        'CountyID',
        'Message',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'ResourceTypeID');
    }

    public function county()
    {
        return $this->belongsTo(County::class, 'CountyID');
    }
}
