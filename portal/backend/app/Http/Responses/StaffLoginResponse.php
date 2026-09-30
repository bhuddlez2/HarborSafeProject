<?php

namespace App\Http\Responses;

use App\Filament\StaffLanding;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/*
Replaces Filament's default LoginResponse, which redirects to Filament::getUrl()
and so lands wherever the navigation happens to start. Bound in
StaffPanelProvider::register().

intended() is kept deliberately: someone who followed a deep link, got bounced
to the login screen and signed in should end up where they were going. The role
map is the fallback for a plain sign-in, replacing Filament's nav-order guess.
*/
class StaffLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->intended(
            StaffLanding::urlFor($request->user()) ?? Filament::getUrl(),
        );
    }
}
