<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/*
"Stay signed in" on the idle-logout warning
(resources/views/filament/partials/idle-logout.blade.php).

Does nothing but answer. Reaching it through the web middleware is the point:
the session is saved on every request, which restarts its 15 idle minutes.
Only that button calls it, never a timer - a timer would keep an unattended
screen signed in forever, which is exactly what the idle logout prevents.

Behind EnsureStaffPanelAccess, so an expired session gets the sign-in redirect
instead of a 204, and the warning reloads the page to show the sign-in screen.
*/
class KeepSessionAliveController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
