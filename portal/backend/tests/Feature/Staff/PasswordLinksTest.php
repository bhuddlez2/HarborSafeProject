<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Auth\SetUpPassword;
use App\Filament\Resources\OfficerAccounts\Pages\ListOfficerAccounts;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Models\User;
use App\Notifications\StaffPasswordLink;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

/*
Passwords by emailed link (App\Filament\Accounts\SetupLink, App\Filament\Pages\Auth):
a new account is created with no password and emailed a set-up link (24
hours); anyone can ask for a reset link from the sign-in page (15 minutes).
Each link works once, only on its own page, and only for an active account.
*/

beforeEach(function () {
    Filament::setCurrentPanel('staff');
    // The password rule checks Have I Been Pwned; never call it from a test.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

afterEach(fn () => cleanupStaffData());

const LINK_TEST_PASSWORD = 'A-long-enough-test-passphrase-7';

function pendingStaffUser(UserRole $role = UserRole::Secretary, bool $isActive = true): User
{
    $user = staffUser($role, $isActive);
    $user->forceFill(['password' => null])->save();

    return $user->fresh();
}

// The email and token in the last link emailed to $user.
function lastPasswordLink(User $user): array
{
    $notification = Notification::sent($user, StaffPasswordLink::class)->last();

    expect($notification)->not->toBeNull();
    parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);

    return ['email' => $query['email'], 'token' => $query['token'], 'setup' => $notification->setup, 'url' => $notification->url];
}

// Both pages allow two submissions a minute per IP (Filament's own limit),
// which these tests pass on purpose; the cache holds nothing else here.
function choosePassword(string $page, array $link)
{
    cache()->flush();

    return Livewire::test($page, ['email' => $link['email'], 'token' => $link['token']])
        ->fillForm(['password' => LINK_TEST_PASSWORD, 'passwordConfirmation' => LINK_TEST_PASSWORD])
        ->call('resetPassword');
}

function requestReset(string $email)
{
    cache()->flush();

    return Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $email])
        ->call('request');
}

test('the emails are sent at once, never queued, and carry only the link', function () {
    expect(new StaffPasswordLink)->not->toBeInstanceOf(ShouldQueue::class)
        ->and(app(FilamentResetPassword::class, ['token' => 'x']))->toBeInstanceOf(StaffPasswordLink::class);

    $user = staffUser(UserRole::Secretary);

    foreach ([true, false] as $setup) {
        $notification = new StaffPasswordLink(setup: $setup);
        $notification->url = 'https://example.test/link';
        $mail = $notification->toMail($user);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        expect($mail->actionUrl)->toBe('https://example.test/link')
            ->and($text)->toContain($setup ? 'expires in 24 hours' : 'expires in 15 minutes')
            ->not->toContain($user->first_name)
            ->not->toContain($user->email);
    }
});

test('a new account has no password and is emailed a set-up link', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $email = staffRunToken().'-new@pest.test';

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(['first_name' => 'Jordan', 'last_name' => 'Rivera', 'email' => $email])
        ->call('create')
        ->assertHasNoErrors()
        ->assertMountedActionModalSee("A set-up link has been emailed to {$email}");

    $user = User::where('email', $email)->firstOrFail();
    $link = lastPasswordLink($user);

    expect($user->hasPendingPassword())->toBeTrue()
        ->and($link['setup'])->toBeTrue()
        ->and($link['url'])->toContain('/set-password?');
});

test('a failed set-up email still saves the account and says so', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $email = staffRunToken().'-nomail@pest.test';

    app()->instance(ChannelManager::class, new class
    {
        public function send(): void
        {
            throw new TransportException('Mail server unreachable');
        }
    });

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(['first_name' => 'Jordan', 'last_name' => 'Rivera', 'email' => $email])
        ->call('create')
        ->assertHasNoErrors()
        ->assertMountedActionModalSee("The set-up email couldn't be sent");

    expect(User::where('email', $email)->exists())->toBeTrue();
});

test('a pending account cannot sign in', function () {
    $user = pendingStaffUser();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => ''])
        ->call('authenticate')
        ->assertHasFormErrors();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

test('the set-up link sets the password once, and then the account can sign in', function () {
    $user = pendingStaffUser();
    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ListStaffAccounts::class)->callTableAction('resendSetupLink', $user);
    auth()->logout();

    $link = lastPasswordLink($user);

    // The signed link opens the page; a tampered one doesn't.
    $this->get($link['url'])->assertOk()->assertSee('Set Up Your Password');
    $this->get(str_replace('token=', 'token=x', $link['url']))->assertForbidden();

    choosePassword(SetUpPassword::class, $link)->assertHasNoErrors()->assertRedirect();

    $user->refresh();
    expect($user->hasPendingPassword())->toBeFalse()
        ->and(Hash::check(LINK_TEST_PASSWORD, $user->password))->toBeTrue();

    // Works once.
    $user->forceFill(['password' => null])->save();
    choosePassword(SetUpPassword::class, $link);
    expect($user->fresh()->hasPendingPassword())->toBeTrue();
});

test('a set-up link expires after 24 hours', function () {
    $user = pendingStaffUser();
    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ListStaffAccounts::class)->callTableAction('resendSetupLink', $user);
    auth()->logout();

    $this->travel(24 * 60 + 1)->minutes();
    choosePassword(SetUpPassword::class, lastPasswordLink($user));

    expect($user->fresh()->hasPendingPassword())->toBeTrue();
});

test('Forgot password? emails an active account a reset link that works once', function () {
    $user = staffUser(UserRole::Secretary);

    requestReset($user->email)->assertNotified('Check your email');
    $link = lastPasswordLink($user);

    expect($link['setup'])->toBeFalse()
        ->and($link['url'])->toContain('/password-reset/reset?');

    choosePassword(ResetPassword::class, $link)->assertHasNoErrors()->assertRedirect();
    expect(Hash::check(LINK_TEST_PASSWORD, $user->fresh()->password))->toBeTrue();

    $user->forceFill(['password' => 'password'])->save();
    choosePassword(ResetPassword::class, $link);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a reset link expires after 15 minutes', function () {
    $user = staffUser(UserRole::Secretary);
    requestReset($user->email);

    $this->travel(16)->minutes();
    choosePassword(ResetPassword::class, lastPasswordLink($user))
        ->assertNotified('This link has expired or has already been used. Please ask for a new one.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('Forgot password? answers the same for an unknown, inactive or just-emailed address', function () {
    $inactive = staffUser(UserRole::Secretary, isActive: false);
    $active = staffUser(UserRole::Secretary);
    requestReset($active->email);

    foreach (['nobody-'.staffRunToken().'@pest.test', $inactive->email, $active->email] as $email) {
        requestReset($email)
            ->assertNotified('Check your email')
            ->assertSchemaStateSet(['email' => null]);
    }

    Notification::assertNotSentTo($inactive, StaffPasswordLink::class);
    expect(Notification::sent($active, StaffPasswordLink::class))->toHaveCount(1);
});

test('each page accepts only its own kind of link', function () {
    $user = pendingStaffUser();
    requestReset($user->email);
    $reset = lastPasswordLink($user);

    // A 15-minute reset link can't be used for 24 hours on the set-up page.
    choosePassword(SetUpPassword::class, $reset);
    expect($user->fresh()->hasPendingPassword())->toBeTrue();

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ListStaffAccounts::class)->callTableAction('resendSetupLink', $user);
    auth()->logout();

    choosePassword(ResetPassword::class, lastPasswordLink($user));
    expect($user->fresh()->hasPendingPassword())->toBeTrue();
});

test('sign-in links to Forgot password? within keyboard reach, after the Sign in button', function () {
    $html = $this->get('/login')->assertOk()->getContent();
    $link = '<a href="'.e(Filament::getRequestPasswordResetUrl()).'" class="hs-forgot-password">Forgot password?</a>';

    expect($html)->toContain($link)
        ->and(strpos($html, $link))->toBeGreaterThan(strpos($html, 'type="password"'))
        ->and(strpos($html, $link))->toBeGreaterThan(strpos($html, 'type="submit"'));
});

test('pending accounts are tagged, and only they can be sent a new set-up link', function () {
    $pending = pendingStaffUser();
    $set = staffUser(UserRole::Secretary);
    $inactive = pendingStaffUser(isActive: false);
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListStaffAccounts::class)
        ->assertSee('Pending')
        ->assertTableActionVisible('resendSetupLink', $pending)
        ->assertTableActionHidden('resendSetupLink', $set)
        ->assertTableActionHidden('resendSetupLink', $inactive)
        ->callTableAction('resendSetupLink', $pending)
        ->assertNotified('Set-up link sent')
        // A second within the minute is refused, not sent.
        ->callTableAction('resendSetupLink', $pending)
        ->assertNotified('A link was just sent');

    expect(Notification::sent($pending, StaffPasswordLink::class))->toHaveCount(1);
});

test('a police admin can resend set-up links only to its own agency\'s officers', function () {
    $w = pestTwoAgencies();
    $w->officerA->forceFill(['password' => null])->save();
    $w->officerB->forceFill(['password' => null])->save();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListOfficerAccounts::class)
        ->assertTableActionVisible('resendSetupLink', $w->officerA)
        ->callTableAction('resendSetupLink', $w->officerA);

    expect(Notification::sent($w->officerA, StaffPasswordLink::class))->toHaveCount(1);

    // Not in the list at all, so no button to press.
    Livewire::test(ListOfficerAccounts::class)->assertCanNotSeeTableRecords([$w->officerB]);
});
