<?php

use App\Enums\UserRole;
use App\Filament\Resources\OfficerAccounts\Pages\CreateOfficerAccount;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
How every create-account page looks and finishes (AccountFormSections,
ConfirmsAccountCreated), on Accounts and Officers alike:

- a full-width Details section - Role | Email, then First name | Last name,
  then anything role-specific - and a full-width Password section under it;
- one Create button, no "Create & create another";
- on success an "Account Created Successfully" pop-up, and an empty form
  behind it for the next account. A failed save shows no pop-up.
*/

afterEach(fn () => cleanupStaffData());

// The password rule checks Have I Been Pwned; never call it from a test.
beforeEach(fn () => Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]));

const ACCOUNT_FORM_PASSWORD = 'a-long-enough-test-passphrase';

function accountFormData(array $overrides = []): array
{
    return [
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'email' => staffRunToken().'-form@pest.test',
        'password' => ACCOUNT_FORM_PASSWORD,
        'passwordConfirmation' => ACCOUNT_FORM_PASSWORD,
        ...$overrides,
    ];
}

// [section heading => [field names in order]], visible fields only.
function accountFormLayout(Testable $page): array
{
    return collect($page->instance()->form->getComponents())
        ->filter(fn ($component): bool => $component instanceof Section)
        ->mapWithKeys(fn (Section $section): array => [
            (string) $section->getHeading() => collect($section->getChildSchema()->getComponents())
                ->filter(fn ($field): bool => $field->isVisible())
                ->map(fn ($field): string => $field->getName())
                ->values()
                ->all(),
        ])
        ->all();
}

test('every create-account page has the same full-width layout', function (Closure $makeUser, string $page, array $details) {
    $this->actingAs($makeUser());

    $form = Livewire::test($page);

    expect(accountFormLayout($form))->toBe([
        'Details' => $details,
        'Password' => ['password', 'passwordConfirmation'],
    ]);

    foreach ($form->instance()->form->getComponents() as $section) {
        expect($section->getColumnSpan())->toBe(['default' => 'full']);
    }
})->with([
    'admin on Accounts' => [
        fn () => staffUser(UserRole::Admin),
        CreateStaffAccount::class,
        ['role_label', 'email', 'first_name', 'last_name'],
    ],
    'super admin on Accounts' => [
        fn () => staffUser(UserRole::SuperAdmin),
        CreateStaffAccount::class,
        ['role', 'email', 'first_name', 'last_name'],
    ],
    'police admin on Officers' => [
        fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Layout PD')),
        CreateOfficerAccount::class,
        ['role_label', 'email', 'first_name', 'last_name', 'badge_number'],
    ],
]);

test('a fixed role shows greyed out on every create page', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(CreateStaffAccount::class)
        ->assertFormSet(['role_label' => 'Secretary'])
        ->assertFormFieldDisabled('role_label');

    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Fixed role PD')));
    Livewire::test(CreateOfficerAccount::class)
        ->assertFormSet(['role_label' => 'Officer'])
        ->assertFormFieldDisabled('role_label');
});

test('a created account shows the pop-up and leaves an empty form behind it', function (Closure $makeUser, string $page, array $extra) {
    $this->actingAs($makeUser());

    // While the pop-up is open, Filament's form assertions read the pop-up,
    // so the page's own form is checked once it is closed.
    Livewire::test($page)
        ->assertActionDoesNotExist('createAnother')
        ->fillForm(accountFormData($extra))
        ->call('create')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertActionMounted('accountCreated')
        ->assertMountedActionModalSee('Account Created Successfully')
        ->call('unmountAction')
        ->assertActionNotMounted('accountCreated')
        ->assertFormSet(['first_name' => null, 'last_name' => null, 'email' => null]);

    expect(User::where('email', staffRunToken().'-form@pest.test')->exists())->toBeTrue();
})->with([
    'Accounts' => [fn () => staffUser(UserRole::Admin), CreateStaffAccount::class, []],
    'Officers' => [
        fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Pop-up PD')),
        CreateOfficerAccount::class,
        ['badge_number' => 'B-900'],
    ],
]);

test('a failed save shows no pop-up', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(accountFormData(['email' => 'not-an-email']))
        ->call('create')
        ->assertHasFormErrors(['email'])
        ->assertActionNotMounted('accountCreated');
});
