<?php

use App\Enums\UserRole;

/*
Staff sessions end after 15 idle minutes for every role, and when the browser
quits (roadmap Phase V). The server-side expiry is Laravel's own; these tests
pin what this project adds to it: the shipped values, the officer Home's
wording, and the script that sends an idle screen back to the sign-in page.

The shipped values are read from the files rather than from config(), because
config() here reflects whichever .env the machine running the tests has.
*/

afterEach(fn () => cleanupStaffData());

test('the shipped settings are 15 minutes and end on browser close', function () {
    $config = file_get_contents(config_path('session.php'));
    $example = file_get_contents(base_path('.env.example'));

    expect($config)
        ->toContain("env('SESSION_LIFETIME', 15)")
        ->toContain("env('SESSION_EXPIRE_ON_CLOSE', true)");

    expect($example)
        ->toMatch('/^SESSION_LIFETIME=15$/m')
        ->toMatch('/^SESSION_EXPIRE_ON_CLOSE=true$/m');
});

// Signed in through the session rather than actingAs(), which would keep the
// user on the guard whatever the session says. Each request carries the same
// session cookie, as a browser would, and starts like a fresh request: the
// in-memory session and guard are emptied after every one, so the next has
// to read the session back from its handler - which is what expires it.
test('an idle session is signed out once the lifetime has passed', function (UserRole $role) {
    config(['session.lifetime' => 15]);
    $user = staffUser($role);

    $this->withSession([auth()->guard('web')->getName() => $user->getAuthIdentifier()]);
    $cookie = [config('session.cookie') => session()->getId()];
    $home = function () use ($cookie) {
        $response = $this->withCookies($cookie)->get('/');
        session()->flush();
        auth()->forgetGuards();

        return $response->headers->get('Location');
    };

    expect($home())->not->toBe(url('/login'));

    $this->travel(14)->minutes();
    expect($home())->not->toBe(url('/login'));

    $this->travel(16)->minutes();
    expect($home())->toBe(url('/login'));
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
]);

test('the Home notice says the session logs out', function (UserRole $role) {
    config(['session.lifetime' => 15]);

    $this->actingAs(staffUser($role))
        ->get(route('filament.staff.pages.police'))
        ->assertOk()
        ->assertSee('Session logs out after 15 minutes of inactivity')
        ->assertDontSee('Session locks');
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
]);

test('every signed-in role gets the idle-logout script with the session lifetime', function (UserRole $role) {
    config(['session.lifetime' => 15]);

    $this->actingAs(staffUser($role))
        ->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertSee('hs-last-server-request', false)
        ->assertSee('const lifetimeMs = 900000;', false);
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
]);

test('the sign-in page carries no idle-logout script or warning', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('hs-last-server-request', false)
        ->assertDontSee('hs-idle-warning', false);
});

// The warning two minutes before the deadline: a pop-up that can't be
// dismissed by clicking away or Esc, whose two buttons keep the session or
// sign out through Filament's own logout route.
test('every signed-in role gets the warning, two minutes before the deadline', function (UserRole $role) {
    config(['session.lifetime' => 15]);

    $this->actingAs(staffUser($role))
        ->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertSee('id="hs-idle-warning"', false)
        ->assertSee('role="alertdialog"', false)
        ->assertSee("You'll be signed out soon")
        ->assertSee('Stay signed in')
        ->assertSee('Sign out now')
        ->assertSee('const warningMs = 120000;', false)
        ->assertSee('action="'.route('filament.staff.auth.logout').'"', false)
        ->assertSee(str_replace('/', '\/', route('staff.session.keep-alive')), false);
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
    'super admin' => UserRole::SuperAdmin,
]);

test('a short session lifetime warns for half of it instead of two minutes', function () {
    config(['session.lifetime' => 3]);

    $this->actingAs(staffUser(UserRole::Admin))
        ->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertSee('const lifetimeMs = 180000;', false)
        ->assertSee('const warningMs = 90000;', false);
});

test('the warning is only ever closed by its buttons', function () {
    $partial = file_get_contents(resource_path('views/filament/partials/idle-logout.blade.php'));

    expect($partial)
        ->toContain(':close-button="false"')
        ->toContain(':close-by-clicking-away="false"')
        ->toContain(':close-by-escaping="false"');
});

test('Stay signed in answers an active staff member with an empty 204', function (UserRole $role) {
    $this->actingAs(staffUser($role))
        ->post(route('staff.session.keep-alive'))
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
    'super admin' => UserRole::SuperAdmin,
]);

// An expired session must not be revived: the button gets the sign-in
// redirect (not a 500, see EnsureStaffPanelAccess), and the page reloads.
test('Stay signed in cannot revive a session that has already ended', function () {
    $this->post(route('staff.session.keep-alive'))
        ->assertRedirect(url('/login'));

    $this->actingAs(staffUser(UserRole::Admin, isActive: false))
        ->post(route('staff.session.keep-alive'))
        ->assertForbidden();
});

// Same session-cookie technique as the expiry test above: Stay signed in at
// minute 14 restarts the clock, so minute 28 is still signed in, and the
// session still ends 15 idle minutes after that request.
test('Stay signed in restarts the 15 idle minutes', function () {
    config(['session.lifetime' => 15]);
    $user = staffUser(UserRole::LawEnforcement);

    $this->withSession([auth()->guard('web')->getName() => $user->getAuthIdentifier()]);
    $cookie = [config('session.cookie') => session()->getId()];
    $fresh = function () {
        session()->flush();
        auth()->forgetGuards();
    };
    $home = function () use ($cookie, $fresh) {
        $location = $this->withCookies($cookie)->get('/')->headers->get('Location');
        $fresh();

        return $location;
    };

    expect($home())->not->toBe(url('/login'));

    $this->travel(14)->minutes();
    $this->withCookies($cookie)->post(route('staff.session.keep-alive'))->assertNoContent();
    $fresh();

    $this->travel(14)->minutes();
    expect($home())->not->toBe(url('/login'));

    $this->travel(16)->minutes();
    expect($home())->toBe(url('/login'));
});
