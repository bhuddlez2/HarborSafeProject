<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/*
One staff-panel login per role, plus a second officer, an inactive admin and a
second agency, for local development only. Deliberately not registered in
DatabaseSeeder.

  php artisan db:seed --class=LocalStaffUserSeeder --database=Portal

Two agencies, each with a police admin and officers, all holding a
law_enforcement_agents row:

  Local Test PD   police-admin@ (LOCAL-9001), officer@ (LOCAL-1001),
                  officer2@ (LOCAL-1002)
  Second Test PD  police-admin2@ (LOCAL-9002), officer3@ (LOCAL-2001)

The second officer exists so "own submissions only" can be checked, and the
second agency so "own agency only" can be; LocalAssessmentSeeder gives each
officer records.

Password comes from LOCAL_SEED_PASSWORD, falling back to "password" when it is
unset or blank. Idempotent: keyed on email (agency on name, agent on user_id),
so re-running resets first/last name, role, password, is_active, badge and agency in place.
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
            ['admin@harborsafe.test', 'Local', 'Admin', UserRole::Admin, true],
            ['secretary@harborsafe.test', 'Local', 'Secretary', UserRole::Secretary, true],
            ['police-admin@harborsafe.test', 'Local', 'Police Admin', UserRole::PoliceAdmin, true],
            ['officer@harborsafe.test', 'Local', 'Officer', UserRole::LawEnforcement, true],
            ['officer2@harborsafe.test', 'Local', 'Officer Two', UserRole::LawEnforcement, true],
            ['inactive@harborsafe.test', 'Inactive', 'Admin', UserRole::Admin, false],
            ['police-admin2@harborsafe.test', 'Second', 'Police Admin', UserRole::PoliceAdmin, true],
            ['officer3@harborsafe.test', 'Second PD', 'Officer', UserRole::LawEnforcement, true],
        ];

        foreach ($users as [$email, $firstName, $lastName, $role, $isActive]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => $password,
                    'role' => $role,
                    'is_active' => $isActive,
                ],
            );
        }

        // agencies and law_enforcement_agents have no created_at/updated_at
        // columns, but neither model turns timestamps off, so a plain
        // create() fails on the missing columns.
        // Agency name => [email => badge]. The police admins' rows are what
        // User::agencyId() reads to scope them to their agency.
        $agencies = [
            'Local Test PD' => [
                'police-admin@harborsafe.test' => 'LOCAL-9001',
                'officer@harborsafe.test' => 'LOCAL-1001',
                'officer2@harborsafe.test' => 'LOCAL-1002',
            ],
            'Second Test PD' => [
                'police-admin2@harborsafe.test' => 'LOCAL-9002',
                'officer3@harborsafe.test' => 'LOCAL-2001',
            ],
        ];

        foreach ($agencies as $agencyName => $badges) {
            $agency = Agency::withoutTimestamps(
                fn (): Agency => Agency::firstOrCreate(['name' => $agencyName]),
            );

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
}
