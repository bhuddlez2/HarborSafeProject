<?php

namespace App\Models;

class County extends BaseModel
{
    protected $connection = 'Feedback';

    protected $table = 'counties';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'ChangeDate',
    ];
}
