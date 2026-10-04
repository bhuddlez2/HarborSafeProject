<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/*
Applies the staff panel's own entry rule - User::canAccessPanel() - to a route
that lives outside the panel.

Needed because the panel's file route (routes/web.php) is served by an ordinary
controller, so none of Filament's gating runs on it. Without this, any
authenticated user could read any uploaded file, a deactivated account
included, which section 5 rule 4 of Filament_CMS_Design.md forbids.

THIS DOES THE AUTHENTICATION CHECK ITSELF rather than sitting behind the `auth`
middleware. Two reasons, both found the hard way:

  - Laravel's `auth` redirects an anonymous visitor to a route named `login`,
    which this application does not have. Sign-in is Filament's, at
    filament.staff.auth.login, so `auth` turns an anonymous request into a
    500 "Route [login] not defined" instead of a redirect.
  - Filament's own Authenticate middleware resolves its guard from the current
    panel, and there is no panel context on a plain web route.

Reusing canAccessPanel() rather than re-listing roles keeps one rule in one
place, and is_active is already part of it.
*/
class EnsureStaffPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(
                filament()->getPanel('staff')->getLoginUrl(),
            );
        }

        if (! $user instanceof User || ! $user->canAccessPanel(filament()->getPanel('staff'))) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
