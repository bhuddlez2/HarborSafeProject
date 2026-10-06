<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\OfficerAccounts\Pages\CreateOfficerAccount;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\Agency;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
Account set-up on the content-management side. Section 5, settled
2026-10-06: admin creates secretaries - never officers, police admins or other
admins - and nobody else creates anything here. Driven through Livewire for
the create/edit paths, because forcing the role and building `name` from its
parts only happen on submit.
*/

afterEach(fn () => cleanupStaffData());

// The password rule checks Have I Been Pwned; never call it from a test.
beforeEach(fn () => Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]));

const STAFF_ACCOUNT_PASSWORD = 'a-long-enough-test-passphrase';

function newSecretaryForm(array $overrides = []): array
{
    return [
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'email' => staffRunToken().'-new@pest.test',
        'password' => STAFF_ACCOUNT_PASSWORD,
        'passwordConfirmation' => STAFF_ACCOUNT_PASSWORD,
        ...$overrides,
    ];
}

test('only an active admin can open the accounts screen', function () {
    $cases = [
        'admin' => [staffUser(UserRole::Admin), true],
        'inactive admin' => [staffUser(UserRole::Admin, isActive: false), false],
        'secretary' => [staffUser(UserRole::Secretary), false],
        'police admin' => [staffUser(UserRole::PoliceAdmin), false],
        'officer' => [staffUser(UserRole::LawEnforcement), false],
    ];

    foreach ($cases as $label => [$user, $expected]) {
        $this->actingAs($user);

        expect(StaffAccountResource::canAccess())->toBe($expected, "accounts screen for: {$label}")
            ->and(StaffAccountResource::canCreate())->toBe($expected, "creating accounts for: {$label}");
    }
});

test('an admin creates an active secretary, with name built from its parts', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(newSecretaryForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', staffRunToken().'-new@pest.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Secretary)
        ->and($user->is_active)->toBeTrue()
        ->and($user->first_name)->toBe('Jordan')
        ->and($user->last_name)->toBe('Rivera')
        ->and($user->name)->toBe('Jordan Rivera');
});

test('the role cannot be chosen by the request', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    // Not a field on the form; a tampered payload must still make a secretary.
    Livewire::test(CreateStaffAccount::class)
        ->fillForm(newSecretaryForm(['role' => UserRole::Admin->value]))
        ->call('create');

    expect(User::where('email', staffRunToken().'-new@pest.test')->firstOrFail()->role)
        ->toBe(UserRole::Secretary);
});

test('each name part is required and capped at 50 characters', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(newSecretaryForm(['first_name' => str_repeat('a', 51), 'last_name' => str_repeat('b', 51)]))
        ->call('create')
        ->assertHasFormErrors(['first_name' => 'max', 'last_name' => 'max']);

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(newSecretaryForm(['first_name' => '', 'last_name' => '']))
        ->call('create')
        ->assertHasFormErrors(['first_name' => 'required', 'last_name' => 'required']);

    // Exactly 50 is allowed.
    Livewire::test(CreateStaffAccount::class)
        ->fillForm(newSecretaryForm(['first_name' => str_repeat('a', 50), 'last_name' => str_repeat('b', 50)]))
        ->call('create')
        ->assertHasNoFormErrors();
});

test('an admin sees and edits secretaries only, never other roles or itself', function () {
    $admin = staffUser(UserRole::Admin);
    $secretary = staffUser(UserRole::Secretary);
    $others = [
        staffUser(UserRole::Admin),
        staffUser(UserRole::PoliceAdmin),
        staffUser(UserRole::LawEnforcement),
        $admin,
    ];

    $this->actingAs($admin);

    $listed = StaffAccountResource::getEloquentQuery()
        ->whereIn('id', [$secretary->getKey(), ...array_map(fn (User $u) => $u->getKey(), $others)])
        ->pluck('id')
        ->all();

    expect($listed)->toBe([$secretary->getKey()])
        ->and(StaffAccountResource::canEdit($secretary))->toBeTrue()
        ->and(StaffAccountResource::canDelete($secretary))->toBeFalse();

    foreach ($others as $other) {
        expect(StaffAccountResource::canEdit($other))->toBeFalse("may not edit a {$other->role->value}");
    }
});

test('editing renames and deactivates, and a blank password keeps the old one', function () {
    $secretary = staffUser(UserRole::Secretary);
    $oldHash = $secretary->password;

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(EditStaffAccount::class, ['record' => $secretary->getRouteKey()])
        ->fillForm([
            'first_name' => 'Casey',
            'last_name' => 'Morgan',
            'is_active' => false,
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $secretary->refresh();

    expect($secretary->name)->toBe('Casey Morgan')
        ->and($secretary->is_active)->toBeFalse()
        ->and($secretary->hasActiveRole(UserRole::Secretary))->toBeFalse()
        ->and($secretary->password)->toBe($oldHash)
        ->and($secretary->role)->toBe(UserRole::Secretary);
});

test('name is always rebuilt from first and last name, whatever is written to it', function () {
    $secretary = staffUser(UserRole::Secretary);
    $secretary->update(['first_name' => 'Jordan', 'last_name' => 'Rivera']);

    expect($secretary->refresh()->name)->toBe('Jordan Rivera');

    // A direct write to name - nothing should do this any more - is overwritten.
    $secretary->update(['name' => 'Someone Else']);

    expect($secretary->refresh()->name)->toBe('Jordan Rivera');
});

test('the account page edits first and last name', function () {
    $secretary = staffUser(UserRole::Secretary);
    $this->actingAs($secretary);

    Livewire::test(EditProfile::class)
        ->assertSchemaStateSet(['first_name' => 'Pest', 'last_name' => UserRole::Secretary->value])
        ->fillForm(['first_name' => 'Casey', 'last_name' => str_repeat('m', 51)])
        ->call('save')
        ->assertHasFormErrors(['last_name' => 'max'])
        ->fillForm(['first_name' => 'Casey', 'last_name' => 'Morgan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($secretary->refresh()->name)->toBe('Casey Morgan')
        ->and($secretary->role)->toBe(UserRole::Secretary);
});

test('a police admin creates an officer with first and last name', function () {
    $agency = Agency::create(['name' => staffRunToken().' Name PD']);
    $policeAdmin = staffUser(UserRole::PoliceAdmin);
    LawEnforcementAgent::create([
        'user_id' => $policeAdmin->getKey(),
        'badge_number' => 'PA-1',
        'agency_id' => $agency->getKey(),
    ]);

    $this->actingAs($policeAdmin);

    Livewire::test(CreateOfficerAccount::class)
        ->fillForm(newSecretaryForm(['badge_number' => 'B-123', 'first_name' => str_repeat('a', 51)]))
        ->call('create')
        ->assertHasFormErrors(['first_name' => 'max'])
        ->fillForm(newSecretaryForm(['badge_number' => 'B-123']))
        ->call('create')
        ->assertHasNoFormErrors();

    $officer = User::where('email', staffRunToken().'-new@pest.test')->firstOrFail();

    expect($officer->role)->toBe(UserRole::LawEnforcement)
        ->and($officer->first_name)->toBe('Jordan')
        ->and($officer->last_name)->toBe('Rivera')
        ->and($officer->name)->toBe('Jordan Rivera')
        ->and($officer->agencyId())->toBe($agency->getKey());
});
