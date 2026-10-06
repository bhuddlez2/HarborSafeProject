<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LawEnforcementAssessment;
use App\Models\User;

// Hand-written, not Shield-generated: access here is the users.role column
// plus ownership (submitted_by) and the police admin's agency, not spatie
// permissions. LawEnforcementAssessmentResource is in resources.exclude in
// config/filament-shield.php so shield:generate never overwrites this file.
// Read-only for everyone - new assessments come from the NewAssessment
// wizard, and editing arrives with the change log.
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

    // Exactly the rows LawEnforcementAssessment::visibleTo() lets the user
    // list - see that scope for the rule. Asking the scope, rather than
    // restating it here, is what keeps this check and the lists in step.
    public function view(User $user, LawEnforcementAssessment $record): bool
    {
        return LawEnforcementAssessment::visibleTo($user)
            ->whereKey($record->getKey())
            ->exists();
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
