<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubmitterInfo extends Model
{
    //Sets up UUID input for new records
    use HasUuids;

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = '_submitter_info';

    //UUID specifications
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;


    //Fillable Columns
    protected $fillable = [
        'SubmitterEmail',
        'SubmitterPhoneNumber',
        'SubmitterFirstName',
        'SubmitterLastName',
    ];

}

