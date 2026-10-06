<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffAccount extends CreateRecord
{
    protected static string $resource = StaffAccountResource::class;

    // Role and is_active are forced here and never read from $data, so a
    // tampered request still creates an active account of a role the creator
    // may manage. With one manageable role (admin -> secretary) that role is
    // the answer; a creator with several will need a role field, validated
    // against manageableRoles() on the server as well as in the form.
    protected function handleRecordCreation(array $data): Model
    {
        $roles = StaffAccountResource::manageableRoles();

        abort_unless(count($roles) === 1, 403);

        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $roles[0],
            'is_active' => true,
        ]);
    }
}
