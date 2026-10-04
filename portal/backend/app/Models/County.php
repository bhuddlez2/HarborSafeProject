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

    // Inverse of ResourceRequestForm::county(). Used by the panel to decide
    // whether a county can still be deleted.
    public function requests()
    {
        return $this->hasMany(ResourceRequestForm::class, 'CountyID');
    }
}
