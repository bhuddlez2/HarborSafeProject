<?php

namespace App\Http\Middleware;

use App\Filament\StaffLanding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
Sends an authenticated user who opens the panel root (/) to their role's
landing page from App\Filament\StaffLanding, instead of letting Filament's
RedirectToHomeController pick the first visible navigation item.

Registered in the panel's authMiddleware, so it only runs once the user is
authenticated - an anonymous visitor is still sent to the login screen first.
Falls through when the map has no entry, leaving Filament's default in place.
*/
class RedirectStaffHome
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.staff.home')) {
            if ($url = StaffLanding::urlFor($request->user())) {
                return redirect($url);
            }
        }

        return $next($request);
    }
}
