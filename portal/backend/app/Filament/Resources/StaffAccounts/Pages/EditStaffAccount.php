<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\LawEnforcementAgent;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditStaffAccount extends EditRecord
{
    protected static string $resource = StaffAccountResource::class;

    // No Delete button: accounts are deactivated, never deleted.
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Only what the form shows. The password is never sent to the browser.
    // The role shows read-only from the record itself (fixedRole()); the
    // agency shows read-only for police admins and officers.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $agent = $record->lawEnforcementAgent;

        return [
            'agency_id' => $agent?->agency_id,
            'badge_number' => $agent?->badge_number,
            'first_name' => $record->first_name,
            'last_name' => $record->last_name,
            'email' => $record->email,
            'is_active' => $record->is_active,
        ];
    }

    // Role and agency are never written, whatever the request carries; a
    // blank password is absent from $data and leaves the current one in
    // place. An officer's badge number is saved with the account, in one
    // Portal transaction.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::connection('Portal')->transaction(function () use ($record, $data): Model {
            $attributes = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'is_active' => (bool) $data['is_active'],
            ];

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $record->update($attributes);

            $isOfficer = ($record->getAttributes()['role'] ?? null) === UserRole::LawEnforcement->value;

            if ($isOfficer && filled($data['badge_number'] ?? null)) {
                // law_enforcement_agents has no created_at/updated_at columns.
                LawEnforcementAgent::withoutTimestamps(
                    fn (): int => LawEnforcementAgent::whereKey($record->getKey())
                        ->update(['badge_number' => $data['badge_number']]),
                );
            }

            return $record;
        });
    }
}
