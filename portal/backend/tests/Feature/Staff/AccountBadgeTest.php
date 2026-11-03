<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Police;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Livewire\Livewire;

/*
Officers change their own badge number on the Account page
(App\Filament\Pages\Auth\EditProfile). Only active law_enforcement users with
a law_enforcement_agents row get the field; saving writes only that row's
badge_number, in the same Portal transaction as the profile, and never the
agency or anyone else's row.
*/

afterEach(fn () => cleanupStaffData());

function badgeOf(User $user): ?string
{
    return LawEnforcementAgent::whereKey($user->getKey())->value('badge_number');
}

function agencyOf(User $user): ?int
{
    return LawEnforcementAgent::whereKey($user->getKey())->value('agency_id');
}

// Every agent row in the fixture, as [user_id => [badge, agency]].
function agentRows(object $w): array
{
    return collect([$w->officerA, $w->officerB, $w->policeAdminA, $w->policeAdminB, $w->secondOfficerA])
        ->mapWithKeys(fn (User $user): array => [$user->getKey() => [badgeOf($user), agencyOf($user)]])
        ->all();
}

function badgeFixture(): object
{
    $w = pestTwoAgencies();
    $w->secondOfficerA = pestAgencyMember(UserRole::LawEnforcement, App\Models\Agency::find(agencyOf($w->officerA)));

    return $w;
}

test('an officer changes their badge on the Account page, trimmed, and nothing else on the row changes', function () {
    $w = badgeFixture();
    $this->actingAs($w->officerA);
    $agency = agencyOf($w->officerA);

    Livewire::test(EditProfile::class)
        ->assertFormFieldVisible('badge_number')
        ->assertFormSet(['badge_number' => badgeOf($w->officerA)])
        ->fillForm(['badge_number' => '  NEW-4821  '])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(badgeOf($w->officerA))->toBe('NEW-4821')
        ->and(agencyOf($w->officerA))->toBe($agency);
});

test('the sidebar identity shows the new badge', function () {
    $w = badgeFixture();
    $this->actingAs($w->officerA);

    Livewire::test(EditProfile::class)
        ->fillForm(['badge_number' => 'SIDEBAR-7310'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(Police::getUrl())
        ->assertOk()
        ->assertSee('Badge SIDEBAR-7310');
});

test('a blank, whitespace-only or too long badge is rejected and the stored one is kept', function (string $badge, string $rule) {
    $w = badgeFixture();
    $this->actingAs($w->officerA);
    $before = badgeOf($w->officerA);

    Livewire::test(EditProfile::class)
        ->fillForm(['badge_number' => $badge])
        ->call('save')
        ->assertHasFormErrors(['badge_number' => $rule]);

    expect(badgeOf($w->officerA))->toBe($before);
})->with([
    'blank' => ['', 'required'],
    'spaces only' => ['    ', 'required'],
    'over 255 characters' => [str_repeat('9', 256), 'max'],
]);

test('tampered agency_id, user_id and role change nothing but the badge', function () {
    $w = badgeFixture();
    $this->actingAs($w->officerA);
    $rows = agentRows($w);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'badge_number' => 'TAMPER-1',
            'agency_id' => agencyOf($w->officerB),
            'user_id' => $w->officerB->getKey(),
            'role' => UserRole::PoliceAdmin->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $rows[$w->officerA->getKey()][0] = 'TAMPER-1';

    expect(agentRows($w))->toBe($rows)
        ->and($w->officerA->refresh()->role)->toBe(UserRole::LawEnforcement)
        ->and(LawEnforcementAgent::whereKey($w->officerA->getKey())->count())->toBe(1);
});

test('another user\'s badge is never touched, in the same agency or another', function () {
    $w = badgeFixture();
    $this->actingAs($w->officerA);
    $rows = agentRows($w);

    Livewire::test(EditProfile::class)
        ->fillForm(['badge_number' => 'MINE-ONLY'])
        ->call('save')
        ->assertHasNoFormErrors();

    unset($rows[$w->officerA->getKey()]);
    $after = agentRows($w);
    unset($after[$w->officerA->getKey()]);

    expect($after)->toBe($rows)
        ->and(badgeOf($w->officerA))->toBe('MINE-ONLY');
});

test('police admins, admins and secretaries have no badge field, and a forced value is ignored', function (string $who) {
    $w = badgeFixture();
    $user = match ($who) {
        'police admin' => $w->policeAdminA,
        'admin' => staffUser(UserRole::Admin),
        'secretary' => staffUser(UserRole::Secretary),
    };
    $this->actingAs($user);
    $rows = agentRows($w);
    $firstName = $user->first_name;

    Livewire::test(EditProfile::class)
        ->assertFormFieldHidden('badge_number')
        ->fillForm(['first_name' => 'Forced', 'badge_number' => 'FORCED-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(agentRows($w))->toBe($rows)
        ->and(LawEnforcementAgent::where('badge_number', 'FORCED-1')->exists())->toBeFalse()
        ->and($user->refresh()->first_name)->toBe($firstName);
})->with(['police admin', 'admin', 'secretary']);

test('an officer with no law_enforcement_agents row has no badge field', function () {
    $officer = staffUser(UserRole::LawEnforcement);
    $this->actingAs($officer);

    Livewire::test(EditProfile::class)
        ->assertFormFieldHidden('badge_number')
        ->fillForm(['badge_number' => 'NO-ROW'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(LawEnforcementAgent::whereKey($officer->getKey())->exists())->toBeFalse();
});
