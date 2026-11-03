<?php

use App\Enums\UserRole;
use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Filament\Tables\TableSearch;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
Character caps on the boxes that had none: the sign-in page, every password
box, and the search box above every table.

Passwords are capped at User::PASSWORD_MAX everywhere one is set *and* at
sign-in, so the sign-in cap can never lock anyone out.
*/

// The breach check calls api.pwnedpasswords.com; never in tests. BREACHED is
// reported as found, every other password as safe. The first matching fake
// wins, so the specific one goes first.
const BREACHED_PASSWORD = 'Correct-horse-battery-staple-1';

beforeEach(function () {
    $hash = strtoupper(sha1(BREACHED_PASSWORD));

    Http::fake([
        'api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).':12345'),
        'api.pwnedpasswords.com/*' => Http::response(''),
    ]);
});

$capped = fn (int $max) => fn (TextInput $field): bool => $field->getMaxLength() === $max;

test('the sign-in boxes are capped', function () use ($capped) {
    Livewire::test(Login::class)
        ->assertSchemaComponentExists('email', checkComponentUsing: $capped(User::EMAIL_MAX))
        ->assertSchemaComponentExists('password', checkComponentUsing: $capped(User::PASSWORD_MAX));
});

test('sign-in refuses an over-long password before checking it', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'nobody@pest.test', 'password' => str_repeat('a', User::PASSWORD_MAX + 1)])
        ->call('authenticate')
        ->assertHasFormErrors(['password' => 'max']);

    expect(auth()->check())->toBeFalse();
});

test('both password boxes on the account forms are capped', function () use ($capped) {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->assertSchemaComponentExists('password', checkComponentUsing: $capped(User::PASSWORD_MAX))
        ->assertSchemaComponentExists('passwordConfirmation', checkComponentUsing: $capped(User::PASSWORD_MAX));

    Livewire::test(EditStaffAccount::class, ['record' => staffUser(UserRole::Secretary)->getKey()])
        ->assertSchemaComponentExists('password', checkComponentUsing: $capped(User::PASSWORD_MAX))
        ->assertSchemaComponentExists('passwordConfirmation', checkComponentUsing: $capped(User::PASSWORD_MAX));
});

test('the Account page cannot set a password sign-in would refuse', function () use ($capped) {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(EditProfile::class)
        ->assertSchemaComponentExists('password', checkComponentUsing: $capped(User::PASSWORD_MAX))
        ->assertSchemaComponentExists('passwordConfirmation', checkComponentUsing: $capped(User::PASSWORD_MAX))
        ->assertSchemaComponentExists('currentPassword', checkComponentUsing: $capped(User::PASSWORD_MAX))
        ->fillForm([
            'password' => str_repeat('a', User::PASSWORD_MAX + 1),
            'passwordConfirmation' => str_repeat('a', User::PASSWORD_MAX + 1),
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasFormErrors(['password' => 'max']);
});

test('the Account page asks for the same password as the account forms', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // 14 characters: Filament's own default (8) would have let it through.
    Livewire::test(EditProfile::class)
        ->fillForm(['password' => 'Short-but-8+ok', 'passwordConfirmation' => 'Short-but-8+ok', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    // Long enough, but found in a breach.
    Livewire::test(EditProfile::class)
        ->fillForm(['password' => BREACHED_PASSWORD, 'passwordConfirmation' => BREACHED_PASSWORD, 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect(Hash::check('password', auth()->user()->fresh()->password))->toBeTrue();
});

test('a table search box stops at the cap in the browser', function () {
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(ListStaffAccounts::class)
        ->assertSeeHtml('maxlength="'.TableSearch::MAX_LENGTH.'"')
        ->assertDontSeeHtml('maxlength="1000"');
});

test('a longer search sent anyway is cut to the cap on the server', function () {
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    $page = Livewire::test(ListStaffAccounts::class)
        ->set('tableSearch', str_repeat('x', TableSearch::MAX_LENGTH + 50))
        ->assertOk();

    expect(mb_strlen($page->instance()->getTableSearch()))->toBe(TableSearch::MAX_LENGTH);
});

test('every table page in the panel caps its search', function () {
    $pages = collect(Filament::getPanel('staff')->getResources())
        ->flatMap(fn (string $resource): array => array_map(
            fn ($registration): string => $registration->getPage(),
            $resource::getPages(),
        ))
        ->filter(fn (string $page): bool => is_subclass_of($page, ListRecords::class))
        ->unique()
        ->values();

    expect($pages)->not->toBeEmpty();

    $missing = $pages->reject(fn (string $page): bool => in_array(CapsTableSearch::class, class_uses_recursive($page), true));

    expect($missing->all())->toBe([], 'These table pages need `use CapsTableSearch;`');
});
