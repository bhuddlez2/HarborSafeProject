<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServiceFeedback extends BaseModel
{
    use HasUuids;

    protected $connection = 'Feedback';

    protected $table = 'service_feedback';

    protected $primaryKey = 'FormID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ServiceID',
        'Rating',
        'Comment',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'ServiceID');
    }
}
