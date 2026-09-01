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
}
