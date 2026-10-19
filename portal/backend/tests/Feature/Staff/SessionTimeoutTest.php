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
        ->assertSee('const deadlineMs = 15 * 60 * 1000', false);
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
]);

test('the sign-in page carries no idle-logout script', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('hs-last-server-request', false);
});
