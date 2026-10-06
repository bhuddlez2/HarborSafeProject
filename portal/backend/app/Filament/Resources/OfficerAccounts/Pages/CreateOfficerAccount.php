<?php

namespace App\Filament\Resources\OfficerAccounts\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOfficerAccount extends CreateRecord
{
    protected static string $resource = OfficerAccountResource::class;

    // Both rows in one Portal transaction. Role, is_active and agency are
    // forced here and never read from $data, so a tampered request still
    // creates an active officer in the police admin's own agency.
    protected function handleRecordCreation(array $data): Model
    {
        $agencyId = Filament::auth()->user()?->managedAgencyId();

        abort_if($agencyId === null, 403);

        return DB::connection('Portal')->transaction(function () use ($data, $agencyId): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::LawEnforcement,
                'is_active' => true,
            ]);

            // law_enforcement_agents has no created_at/updated_at columns.
            LawEnforcementAgent::withoutTimestamps(
                fn (): LawEnforcementAgent => LawEnforcementAgent::create([
                    'user_id' => $user->getKey(),
                    'badge_number' => $data['badge_number'],
                    'agency_id' => $agencyId,
                ]),
            );

            return $user;
        });
    }
}
