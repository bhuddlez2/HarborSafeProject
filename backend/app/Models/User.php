<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

//FOR DB ERRORS
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

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
