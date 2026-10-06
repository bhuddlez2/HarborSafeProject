<?php

namespace App\Models;

class Agency extends BaseModel
{
    protected $connection = 'Portal';

    protected $table = 'agencies';

    // The table has no created_at/updated_at columns.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
