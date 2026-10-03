<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LawEnforcementAssessment;
use App\Models\User;

// Hand-written, not Shield-generated: access here is the users.role column
// plus ownership (submitted_by), not spatie permissions. The resource is in
// resources.exclude in config/filament-shield.php so shield:generate never
// overwrites this file. Read-only for everyone - new assessments come from
// the NewAssessment wizard, and editing arrives with the change log.
class LawEnforcementAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveRole(
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
            UserRole::Admin,
        );
    }

    // Admins see every submission; police admins only those whose submitting
    // officer is in their own agency (none at all without an agency);
    // officers only their own.
    public function view(User $user, LawEnforcementAssessment $record): bool
    {
        if ($user->hasActiveRole(UserRole::Admin)) {
            return true;
        }

        if ($user->hasActiveRole(UserRole::PoliceAdmin)) {
            $agencyId = $user->agencyId();

            return $agencyId !== null && $record->submitter?->agencyId() === $agencyId;
        }

        return $user->hasActiveRole(UserRole::LawEnforcement)
            && (int) $record->submitted_by === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LawEnforcementAssessment $record): bool
    {
        return false;
    }

    public function delete(User $user, LawEnforcementAssessment $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, LawEnforcementAssessment $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, LawEnforcementAssessment $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, LawEnforcementAssessment $record): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
