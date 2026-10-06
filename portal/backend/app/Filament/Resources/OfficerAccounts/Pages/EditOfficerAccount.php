<?php

namespace App\Filament\Resources\OfficerAccounts\Pages;

use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Models\LawEnforcementAgent;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditOfficerAccount extends EditRecord
{
    protected static string $resource = OfficerAccountResource::class;

    // No Delete button: accounts are deactivated, never deleted.
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Only what the form shows. The password is never sent to the browser.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'first_name' => $this->getRecord()->first_name,
            'last_name' => $this->getRecord()->last_name,
            'email' => $this->getRecord()->email,
            'badge_number' => $this->getRecord()->lawEnforcementAgent?->badge_number,
            'is_active' => $this->getRecord()->is_active,
        ];
    }

    // Both rows in one Portal transaction. Role and agency are never
    // written, whatever the request carries; a blank password is absent from
    // $data and leaves the current one in place.
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

            // law_enforcement_agents has no created_at/updated_at columns.
            LawEnforcementAgent::withoutTimestamps(
                fn (): int => LawEnforcementAgent::whereKey($record->getKey())
                    ->update(['badge_number' => $data['badge_number']]),
            );

            return $record;
        });
    }
}
