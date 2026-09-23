<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/*
One staff-panel login per role, plus an inactive admin, for local development
only. Deliberately not registered in DatabaseSeeder.

  php artisan db:seed --class=LocalStaffUserSeeder --database=Portal

Password comes from LOCAL_SEED_PASSWORD, falling back to "password" when it is
unset or blank. Idempotent: keyed on email, so re-running resets name, role,
password and is_active in place.
*/
class LocalStaffUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalStaffUserSeeder only runs when APP_ENV=local.');
        }

        // ?: rather than env()'s default so an empty LOCAL_SEED_PASSWORD= line
        // still falls back instead of seeding blank passwords.
        $password = env('LOCAL_SEED_PASSWORD') ?: 'password';

        $users = [
            ['admin@harborsafe.test', 'Local Admin', UserRole::Admin, true],
            ['secretary@harborsafe.test', 'Local Secretary', UserRole::Secretary, true],
            ['police-admin@harborsafe.test', 'Local Police Admin', UserRole::PoliceAdmin, true],
            ['officer@harborsafe.test', 'Local Officer', UserRole::LawEnforcement, true],
            ['inactive@harborsafe.test', 'Inactive Admin', UserRole::Admin, false],
        ];

        foreach ($users as [$email, $name, $role, $isActive]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'is_active' => $isActive,
                ],
            );
        }
    }
}
