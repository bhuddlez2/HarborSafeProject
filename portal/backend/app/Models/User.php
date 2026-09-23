<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

//FOR DB ERRORS
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    // Checked by Filament at login and on every panel request. Reads the raw
    // role string so an unrecognised value is refused rather than throwing
    // from the enum cast.
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && UserRole::tryFrom((string) ($this->getAttributes()['role'] ?? '')) !== null;
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


