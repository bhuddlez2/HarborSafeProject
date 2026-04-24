<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

//to catch DB routing errors
abstract class BaseModel extends Model
{
    protected function checkConnection(): void
    {
        if(!isset($this->connection)){
            throw new RuntimeException(
                'No database connection set on model: ' . static::class .
                '. All models must explicity define $connection.'
            );
        }
    }

    protected static function booted(): void
    {
        //Runs whenever model is used
        (new static)->checkConnection();
    }

}