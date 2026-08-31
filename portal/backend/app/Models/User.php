<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

//FOR DB ERRORS
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // The users table physically lives on the Portal connection (migrations
    // were run with --database=Portal), not the default mariadb connection
    // this model would otherwise fall back to.
    protected $connection = 'Portal';

    public function lawEnforcementAgent()
    {
        return $this->hasOne(LawEnforcementAgent::class);
    }

    public function submittedAssessments()
    {
        return $this->hasMany(LawEnforcementAssessment::class, 'submitted_by');
    }

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}


