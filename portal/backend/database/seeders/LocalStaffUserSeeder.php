<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/*
One staff-panel login per role, plus a second officer and an inactive admin,
for local development only. Deliberately not registered in DatabaseSeeder.

  php artisan db:seed --class=LocalStaffUserSeeder --database=Portal

Both officers also get a law_enforcement_agents row (badges LOCAL-1001 and
LOCAL-1002) in the "Local Test PD" agency. The second officer exists so "own
submissions only" can be checked; LocalAssessmentSeeder gives each of them
records.

Password comes from LOCAL_SEED_PASSWORD, falling back to "password" when it is
unset or blank. Idempotent: keyed on email (agency on name, agent on user_id),
so re-running resets name, role, password, is_active, badge and agency in place.
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
            ['officer2@harborsafe.test', 'Local Officer Two', UserRole::LawEnforcement, true],
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

        // agencies and law_enforcement_agents have no created_at/updated_at
        // columns, but neither model turns timestamps off, so a plain
        // create() fails on the missing columns.
        $agency = Agency::withoutTimestamps(
            fn (): Agency => Agency::firstOrCreate(['name' => 'Local Test PD']),
        );

        $badges = [
            'officer@harborsafe.test' => 'LOCAL-1001',
            'officer2@harborsafe.test' => 'LOCAL-1002',
        ];

        foreach ($badges as $email => $badge) {
            LawEnforcementAgent::withoutTimestamps(
                fn (): LawEnforcementAgent => LawEnforcementAgent::updateOrCreate(
                    ['user_id' => User::where('email', $email)->value('id')],
                    [
                        'badge_number' => $badge,
                        'agency_id' => $agency->getKey(),
                    ],
                ),
            );
        }
    }
}
