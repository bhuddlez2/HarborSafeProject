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
}
