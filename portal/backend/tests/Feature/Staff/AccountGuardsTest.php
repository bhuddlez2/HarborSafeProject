<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Filament\Resources\OfficerAccounts\Pages\CreateOfficerAccount;
use App\Filament\Resources\OfficerAccounts\Pages\EditOfficerAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
The guards on who may create, change and switch off which accounts (team plan
task 4), enforced on the server rather than by hidden buttons:

- nobody changes their own role or deactivates themselves;
- a creator only ever makes the roles it may create, in its own agency, and
  a tampered request can't change that on create or edit;
- officers can be deactivated, which stops them signing in and shuts out one
  already signed in at their next request.

The rest of the creation table is covered elsewhere: who may open each account
screen in StaffAccountsTest and PoliceAdminAgencyScopeTest, and inactive
accounts against every panel component in PanelAccessTest.
*/

afterEach(fn () => cleanupStaffData());

// The password rule checks Have I Been Pwned; never call it from a test.
beforeEach(fn () => Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]));

const ACCOUNT_GUARD_PASSWORD = 'a-long-enough-test-passphrase';

function newOfficerForm(array $overrides = []): array
{
    return [
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'email' => staffRunToken().'-officer@pest.test',
        'badge_number' => 'B-100',
        'password' => ACCOUNT_GUARD_PASSWORD,
        'passwordConfirmation' => ACCOUNT_GUARD_PASSWORD,
        ...$overrides,
    ];
}

test('nobody changes their own role or switches themselves off on the Account page', function (UserRole $role) {
    $user = staffUser($role);
    $this->actingAs($user);

    // Neither is a field on the page; a tampered payload must change nothing.
    Livewire::test(EditProfile::class)
        ->fillForm([
            'first_name' => 'Casey',
            'last_name' => 'Morgan',
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Casey Morgan')
        ->and($user->role)->toBe($role)
        ->and($user->is_active)->toBeTrue();
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
]);

// Switching users mid-test needs a fresh session: AuthenticateSession keeps
// the first user's password hash there and signs out anyone else.
test('nobody opens their own account on an account screen', function () {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);

    $this->actingAs($admin)
        ->get(StaffAccountResource::getUrl('edit', ['record' => $admin]))
        ->assertNotFound();

    $this->flushSession()
        ->actingAs($w->policeAdminA)
        ->get(OfficerAccountResource::getUrl('edit', ['record' => $w->policeAdminA]))
        ->assertNotFound();
});

test('an account screen opens only the accounts its user may manage', function () {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $secretary = staffUser(UserRole::Secretary);

    // Admin: secretaries only.
    $this->actingAs($admin)
        ->get(StaffAccountResource::getUrl('edit', ['record' => $secretary]))
        ->assertOk();

    foreach ([staffUser(UserRole::Admin), $w->policeAdminA, $w->officerA] as $other) {
        $this->get(StaffAccountResource::getUrl('edit', ['record' => $other]))
            ->assertNotFound();
    }

    // Police admin: its own agency's officers only.
    $this->flushSession()
        ->actingAs($w->policeAdminA)
        ->get(OfficerAccountResource::getUrl('edit', ['record' => $w->officerA]))
        ->assertOk();

    foreach ([$w->officerB, $w->policeAdminB, $admin, $secretary] as $other) {
        $this->get(OfficerAccountResource::getUrl('edit', ['record' => $other]))
            ->assertNotFound();
    }
});

test('editing a secretary never changes its role', function () {
    $secretary = staffUser(UserRole::Secretary);
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(EditStaffAccount::class, ['record' => $secretary->getRouteKey()])
        ->fillForm(['role' => UserRole::Admin->value, 'password' => '', 'passwordConfirmation' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($secretary->refresh()->role)->toBe(UserRole::Secretary);
});

test('a police admin creates only active officers in its own agency, whatever the request says', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    Livewire::test(CreateOfficerAccount::class)
        ->fillForm(newOfficerForm([
            'role' => UserRole::Admin->value,
            'agency_id' => $w->officerB->agencyId(),
            'is_active' => false,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $officer = User::where('email', staffRunToken().'-officer@pest.test')->firstOrFail();

    expect($officer->role)->toBe(UserRole::LawEnforcement)
        ->and($officer->agencyId())->toBe($w->policeAdminA->agencyId())
        ->and($officer->is_active)->toBeTrue();
});

test('a police admin deactivates and reactivates an officer, and nothing else changes', function () {
    $w = pestTwoAgencies();
    $agencyId = $w->officerA->agencyId();
    $this->actingAs($w->policeAdminA);

    $edit = fn (bool $active) => Livewire::test(EditOfficerAccount::class, ['record' => $w->officerA->getRouteKey()])
        ->fillForm([
            'is_active' => $active,
            'role' => UserRole::PoliceAdmin->value,
            'agency_id' => $w->officerB->agencyId(),
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $edit(false);
    $officer = $w->officerA->fresh();

    expect($officer->is_active)->toBeFalse()
        ->and($officer->role)->toBe(UserRole::LawEnforcement)
        ->and($officer->agencyId())->toBe($agencyId)
        ->and($officer->canAccessPanel(Filament::getPanel('staff')))->toBeFalse();

    // Still listed, so it can be switched back on.
    $edit(true);

    expect($w->officerA->fresh()->is_active)->toBeTrue();
});

test('an inactive account cannot sign in', function (UserRole $role) {
    Filament::setCurrentPanel('staff');
    $signIn = fn (User $user) => Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    // The same sign-in works while the account is active, so the refusal
    // below is the inactive flag and not a broken test.
    $active = staffUser($role);
    $signIn($active)->assertHasNoFormErrors();
    expect(auth()->id())->toBe($active->getKey());

    auth()->logout();

    $signIn(staffUser($role, isActive: false))->assertHasFormErrors(['email']);
    expect(auth()->check())->toBeFalse();
})->with([
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
]);

// Signed in through the session rather than actingAs(), so every request
// loads the user afresh from the database, as it would for a real officer;
// see SessionTimeoutTest for why the session and guard are emptied between
// requests.
test('an officer deactivated while signed in is refused at the next request', function () {
    $w = pestTwoAgencies();
    $officer = $w->officerA;

    $this->withSession([auth()->guard('web')->getName() => $officer->getKey()]);
    $cookie = [config('session.cookie') => session()->getId()];
    $open = function () use ($cookie): int {
        $status = $this->withCookies($cookie)->get(route('filament.staff.pages.police'))->getStatusCode();
        session()->flush();
        auth()->forgetGuards();

        return $status;
    };

    expect($open())->toBe(200);

    $officer->update(['is_active' => false]);

    expect($open())->toBe(403);
});
