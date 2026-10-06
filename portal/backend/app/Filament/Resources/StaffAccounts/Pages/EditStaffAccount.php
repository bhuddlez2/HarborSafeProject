<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaffAccount extends EditRecord
{
    protected static string $resource = StaffAccountResource::class;

    // No Delete button: accounts are deactivated, never deleted.
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Only what the form shows. The password is never sent to the browser.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return [
            'first_name' => $record->first_name,
            'last_name' => $record->last_name,
            'email' => $record->email,
            'is_active' => $record->is_active,
        ];
    }

    // Role is never written, whatever the request carries; a blank password
    // is absent from $data and leaves the current one in place.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
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

        return $record;
    }
}
