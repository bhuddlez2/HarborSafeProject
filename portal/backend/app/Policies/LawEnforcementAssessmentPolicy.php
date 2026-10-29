<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LawEnforcementAssessment;
use App\Models\User;

// Access here is the users.role column plus ownership (submitted_by) and the
// police admin's agency. Every method is written out, so nothing falls back
// to "allowed" (the panel runs in strict mode; ActionAccessTest).
// New assessments come only from the NewAssessment wizard (officers, and police
// admins with an agency, each submitting their own). The submitting officer,
// or an active police admin of the submitter's agency, may edit one (update()
// below, section 5 rule 1), always through App\Services\AssessmentEditor,
// which records the change log. Admin is view-only. Nothing else is writable.
class LawEnforcementAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveRole(
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
            UserRole::Admin,
            UserRole::SuperAdmin,
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

    // Section 5 rule 1, widened on purpose for police admins of the SAME
    // agency only: an active officer edits their own submissions; an active
    // police admin with an agency edits any their agency may see - their
    // officers' and their own - by asking visibleTo() rather than restating
    // the agency rule. Admins (view-only) and everyone else: never.
    public function update(User $user, LawEnforcementAssessment $record): bool
    {
        if ($user->hasActiveRole(UserRole::LawEnforcement)) {
            return (int) $record->submitted_by === $user->id;
        }

        if ($user->managedAgencyId() !== null) {
            return LawEnforcementAssessment::visibleTo($user)
                ->whereKey($record->getKey())
                ->exists();
        }

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
