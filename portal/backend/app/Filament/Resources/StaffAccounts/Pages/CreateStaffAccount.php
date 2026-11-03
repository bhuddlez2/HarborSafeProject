<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\ConfirmsAccountCreated;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\Agency;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateStaffAccount extends CreateRecord
{
    use ConfirmsAccountCreated;

    protected static string $resource = StaffAccountResource::class;

    // The role is checked against manageableRoles() here, on the server: a
    // creator with one manageable role always gets that role, whatever the
    // request says, and a creator who picks one must have picked one they
    // may manage. is_active is always forced on. Police admins and officers
    // get their law_enforcement_agents row in the same Portal transaction,
    // with an agency that must exist.
    protected function handleRecordCreation(array $data): Model
    {
        $roles = StaffAccountResource::manageableRoles();

        abort_if($roles === [], 403);

        $role = count($roles) === 1
            ? $roles[0]
            : UserRole::tryFrom((string) ($data['role'] ?? ''));

        abort_unless($role !== null && in_array($role, $roles, true), 403);

        $agencyId = null;

        if ($role->needsAgency()) {
            $agencyId = Agency::query()->whereKey($data['agency_id'] ?? null)->value('id');

            abort_if($agencyId === null, 403);
        }

        return DB::connection('Portal')->transaction(function () use ($data, $role, $agencyId): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'is_active' => true,
            ]);

            if ($role->needsAgency()) {
                // A police admin's row only carries the agency link; the
                // badge column is NOT NULL, so it holds an empty string and
                // User::displayBadge() shows "Police admin" instead.
                // law_enforcement_agents has no created_at/updated_at columns.
                LawEnforcementAgent::withoutTimestamps(
                    fn (): LawEnforcementAgent => LawEnforcementAgent::create([
                        'user_id' => $user->getKey(),
                        'badge_number' => $role === UserRole::LawEnforcement ? $data['badge_number'] : '',
                        'agency_id' => $agencyId,
                    ]),
                );
            }

            return $user;
        });
    }
}
