<?php

namespace App\Models;

class Service extends BaseModel
{
    protected $connection = 'Feedback';

    protected $table = 'services';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'ChangeDate',
    ];

    // Inverse of ServiceFeedback::service(). Used by the panel to decide
    // whether a service can still be deleted.
    public function feedback()
    {
        return $this->hasMany(ServiceFeedback::class, 'ServiceID');
    }
}
