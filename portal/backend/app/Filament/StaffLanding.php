<?php

namespace App\Filament;

use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Pages\Police;
use App\Models\User;

/*
The one place that decides which page each role lands on after signing in.

Filament's own answer is emergent rather than declared: RedirectToHomeController
asks the panel for getRedirectUrl(), which returns the first navigation item the
user can see (vendor/filament/filament/src/Panel/Concerns/HasRoutes.php). That
produced the right result, but it is decided by $navigationSort and navigation
group placement - so adding an ungrouped page with a lower sort would silently
move every role's landing page. Note $panel->homeUrl() does NOT affect this; it
only sets the sidebar brand link.

Both entry points consult this map instead: StaffLoginResponse (after a
successful sign-in) and RedirectStaffHome (someone opening /staff directly).

The content arm points at the cluster, not at a page. Opening a cluster runs
Cluster::mount(), which redirects to the first tab the user can actually see -
Events for both admin and secretary - so there is no separate landing page to
keep in step with the matrix.

Order still matters: the content arm is checked first so admin and secretary
resolve together, before the officer arm is considered.
*/
final class StaffLanding
{
    public static function urlFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return match (true) {
            $user->hasActiveRole(UserRole::Admin, UserRole::Secretary) => ContentCluster::getUrl(),
            $user->hasActiveRole(UserRole::LawEnforcement, UserRole::PoliceAdmin) => Police::getUrl(),
            // Inactive, or a role with no landing page of its own. Callers fall
            // back to Filament's default rather than guessing.
            default => null,
        };
    }
}
