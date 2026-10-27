<?php

use App\Enums\UserRole;
use App\Filament\Forms\AccountFormSections;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Filament\Resources\OfficerAccounts\Pages\CreateOfficerAccount;
use App\Filament\Resources\OfficerAccounts\Pages\EditOfficerAccount;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
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

const ACCOUNT_GUARD_PASSWORD = 'A-long-enough-test-passphrase-7';

// Treated as breached by the fake below; long enough for every other rule.
const BREACHED_TEST_PASSWORD = 'A-breached-test-passphrase-9';

// The password rule checks Have I Been Pwned; never call it from a test. The
// fake lists BREACHED_TEST_PASSWORD's hash as seen in a breach, and nothing else.
beforeEach(fn () => Http::fake([
    'api.pwnedpasswords.com/*' => Http::response(substr(strtoupper(sha1(BREACHED_TEST_PASSWORD)), 5).':42'),
]));

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

// Every role, for the Account page tests.
const ACCOUNT_PAGE_ROLES = [
    'officer' => UserRole::LawEnforcement,
    'police admin' => UserRole::PoliceAdmin,
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
    'super admin' => UserRole::SuperAdmin,
];

// An Account page user. Officers and police admins get a law_enforcement_agents
// row, as they have in real use.
function accountPageUser(UserRole $role): User
{
    return match ($role) {
        UserRole::LawEnforcement => pestTwoAgencies()->officerA,
        UserRole::PoliceAdmin => pestTwoAgencies()->policeAdminA,
        default => staffUser($role),
    };
}

// Everything the Account page could touch, to compare before and after.
function accountSnapshot(User $user): array
{
    $user = $user->fresh();

    return [
        'name' => $user->name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'role' => $user->role,
        'is_active' => $user->is_active,
        'password' => $user->password,
        'agents' => LawEnforcementAgent::orderBy('user_id')->get(['user_id', 'agency_id', 'badge_number'])->toArray(),
    ];
}

test('name and email are shown read-only on the Account page, and a payload changing them is ignored', function (UserRole $role) {
    $user = accountPageUser($role);
    $this->actingAs($user);
    $before = accountSnapshot($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldDisabled('first_name')
        ->assertFormFieldDisabled('last_name')
        ->assertFormFieldDisabled('email')
        ->assertFormSet([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ])
        ->fillForm([
            'first_name' => 'Casey',
            'last_name' => 'Morgan',
            'email' => staffRunToken().'-changed@pest.test',
        ])
        ->call('save')
        // No current-password prompt either: the email isn't changing.
        ->assertHasNoFormErrors();

    expect(accountSnapshot($user))->toBe($before);
})->with(ACCOUNT_PAGE_ROLES);

test('nobody changes their own role, agency or active flag on the Account page', function (UserRole $role) {
    $w = pestTwoAgencies();
    $user = accountPageUser($role);
    $this->actingAs($user);
    $before = accountSnapshot($user);

    // None of these is a field on the page; a tampered payload must change nothing.
    Livewire::test(EditProfile::class)
        ->fillForm([
            'role' => UserRole::SuperAdmin->value,
            'is_active' => false,
            'agency_id' => $w->officerB->agencyId(),
            'user_id' => $w->officerB->getKey(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(accountSnapshot($user))->toBe($before);
})->with(ACCOUNT_PAGE_ROLES);

test('every role changes their own password on the Account page, and nothing else', function (UserRole $role) {
    $user = accountPageUser($role);
    $this->actingAs($user);
    $before = accountSnapshot($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'first_name' => 'Casey',
            'email' => staffRunToken().'-changed@pest.test',
            'password' => ACCOUNT_GUARD_PASSWORD,
            'passwordConfirmation' => ACCOUNT_GUARD_PASSWORD,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $after = accountSnapshot($user);

    expect(Hash::check(ACCOUNT_GUARD_PASSWORD, $after['password']))->toBeTrue()
        ->and(Arr::except($after, 'password'))->toBe(Arr::except($before, 'password'));
})->with(ACCOUNT_PAGE_ROLES);

test('a new password on the Account page follows the account-creation rule', function (string $password) {
    $user = staffUser(UserRole::Secretary);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => $password,
            'passwordConfirmation' => $password,
            'currentPassword' => 'password',
        ])
        ->call('save')
        // A Password rule object reports no rule name, only its message.
        ->assertHasFormErrors(['password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    // Each one breaks exactly one part of the rule.
    '14 characters' => ['Fourteen-ch4rs'],
    '65 characters' => ['A-1'.str_repeat('k', 62)],
    'no capital letter' => ['no-capital-letter-7'],
    'no lowercase letter' => ['NO-LOWERCASE-LETTER-7'],
    'no number' => ['No-Number-Anywhere-Here'],
    'no symbol' => ['NoSymbolAnywhereHere7'],
    'breached' => [BREACHED_TEST_PASSWORD],
    // Off the keyboard: bcrypt would ignore the end of this one (40 é = 80
    // bytes), and a null character used to be a server error.
    'accented letters' => ['A-1'.str_repeat('é', 40)],
    'an emoji' => ['A-long-enough-pass-7 🔒'],
    'a null character' => ["A-long-enough-pass-7\0"],
    'a tab' => ["A-long-enough-pass-7\t"],
]);

test('every keyboard symbol and a space are allowed in a password', function () {
    $user = staffUser(UserRole::Secretary);
    $this->actingAs($user);
    // All 32 symbols on a US keyboard, plus a space, a capital, a lowercase
    // letter and a number - quotes, semicolons and angle brackets included,
    // since a password is only ever hashed.
    $password = 'Aa1 !"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~';

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => $password,
            'passwordConfirmation' => $password,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check($password, $user->fresh()->password))->toBeTrue();
});

test('every new-password box says what the rule is', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(EditProfile::class)->assertSee(AccountFormSections::PASSWORD_HINT);

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)->assertSee(AccountFormSections::PASSWORD_HINT);

    Livewire::test(EditStaffAccount::class, ['record' => staffUser(UserRole::Secretary)->getKey()])
        ->assertSee('Leave blank to keep the current password. '.AccountFormSections::PASSWORD_HINT);

    expect(AccountFormSections::PASSWORD_HINT)->toContain('15 to 64 characters', 'capital letter', 'lowercase letter', 'number', 'symbol', 'breaches');
});

test('a 15-character password is accepted on the Account page', function () {
    $user = staffUser(UserRole::Secretary);
    $this->actingAs($user);
    $password = 'Fifteen-chars-5';

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => $password,
            'passwordConfirmation' => $password,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check($password, $user->fresh()->password))->toBeTrue();
});

test('a wrong current password keeps the old one', function () {
    $user = staffUser(UserRole::Admin);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => ACCOUNT_GUARD_PASSWORD,
            'passwordConfirmation' => ACCOUNT_GUARD_PASSWORD,
            'currentPassword' => 'not-the-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('an officer changes their badge on the Account page, and nothing else on their record changes', function () {
    $officer = accountPageUser(UserRole::LawEnforcement);
    $this->actingAs($officer);
    $before = accountSnapshot($officer);

    Livewire::test(EditProfile::class)
        ->assertFormFieldVisible('badge_number')
        ->fillForm(['first_name' => 'Casey', 'badge_number' => 'GUARD-2207'])
        ->call('save')
        ->assertHasNoFormErrors();

    $expected = $before;
    foreach ($expected['agents'] as &$agent) {
        if ($agent['user_id'] === $officer->getKey()) {
            $agent['badge_number'] = 'GUARD-2207';
        }
    }

    expect(accountSnapshot($officer))->toBe($expected);
});

test('police admins, admins, secretaries and super admins have no badge field, and a forced badge is ignored', function (UserRole $role) {
    $user = accountPageUser($role);
    $this->actingAs($user);
    $before = accountSnapshot($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldHidden('badge_number')
        ->fillForm(['badge_number' => 'FORCED-2207'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(accountSnapshot($user))->toBe($before)
        ->and(LawEnforcementAgent::where('badge_number', 'FORCED-2207')->exists())->toBeFalse();
})->with(Arr::except(ACCOUNT_PAGE_ROLES, 'officer'));

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
        ->assertHasNoErrors();

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
