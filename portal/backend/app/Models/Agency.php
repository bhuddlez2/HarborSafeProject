<?php

namespace App\Models;

class Agency extends BaseModel
{
    protected $connection = 'Portal';

    protected $table = 'agencies';

    protected $fillable = [
        'name',
    ];
}
