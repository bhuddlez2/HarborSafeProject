<?php

use App\Console\Commands\CreateSuperAdmin;
use App\Enums\FollowUpKind;
use App\Enums\UserRole;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\Agency;
use App\Models\FollowUp;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
The super admin role and the Agencies screen (team plan task 15), settled
2026-10-21:

- super admin reaches everything admin does, plus Agencies, and is still
  view-only on assessments: it never submits, edits or deletes one;
- on Accounts it creates every role, other super admins included (so
  ownership can pass on), police admins and officers in an agency that must
  exist, and never manages its own account; the first super admin comes from
  `php artisan staff:create-super-admin`;
- an account's role and agency never change after creation;
- agencies are added and renamed, never deleted.

Which screens each role reaches is PanelAccessTest's job; the sidebar is
NavigationTest's.
*/

afterEach(fn () => cleanupStaffData());

// The password rule checks Have I Been Pwned; never call it from a test.
beforeEach(fn () => Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]));

const SUPER_ADMIN_TEST_PASSWORD = 'A-long-enough-test-passphrase-7';

function superAdminAccountForm(UserRole $role, array $overrides = []): array
{
    return [
        'role' => $role->value,
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'email' => staffRunToken().'-'.$role->value.'@pest.test',
        'password' => SUPER_ADMIN_TEST_PASSWORD,
        'passwordConfirmation' => SUPER_ADMIN_TEST_PASSWORD,
        ...$overrides,
    ];
}

test('a super admin creates each role it manages, with the agency and badge it needs', function () {
    $agency = pestAgency('Created PD');
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    expect(StaffAccountResource::manageableRoles())->toBe([
        UserRole::SuperAdmin, UserRole::Admin, UserRole::Secretary, UserRole::PoliceAdmin, UserRole::LawEnforcement,
    ]);

    $forms = [
        UserRole::SuperAdmin->value => superAdminAccountForm(UserRole::SuperAdmin),
        UserRole::Admin->value => superAdminAccountForm(UserRole::Admin),
        UserRole::Secretary->value => superAdminAccountForm(UserRole::Secretary),
        UserRole::PoliceAdmin->value => superAdminAccountForm(UserRole::PoliceAdmin, ['agency_id' => $agency->getKey()]),
        UserRole::LawEnforcement->value => superAdminAccountForm(UserRole::LawEnforcement, [
            'agency_id' => $agency->getKey(),
            'badge_number' => 'B-501',
        ]),
    ];

    foreach ($forms as $form) {
        Livewire::test(CreateStaffAccount::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasNoErrors();
    }

    $created = fn (UserRole $role): User => User::where('email', staffRunToken().'-'.$role->value.'@pest.test')->firstOrFail();

    foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::Secretary] as $role) {
        expect($created($role)->role)->toBe($role)
            ->and($created($role)->is_active)->toBeTrue()
            ->and($created($role)->lawEnforcementAgent)->toBeNull();
    }

    $policeAdmin = $created(UserRole::PoliceAdmin);
    $officer = $created(UserRole::LawEnforcement);

    expect($policeAdmin->role)->toBe(UserRole::PoliceAdmin)
        ->and($policeAdmin->agencyId())->toBe($agency->getKey())
        ->and($policeAdmin->displayBadge())->toBe(User::POLICE_ADMIN_BADGE)
        ->and($officer->role)->toBe(UserRole::LawEnforcement)
        ->and($officer->agencyId())->toBe($agency->getKey())
        ->and($officer->lawEnforcementAgent->badge_number)->toBe('B-501');
});

test('a super admin deactivates another super admin, but never itself', function () {
    $superAdmin = staffUser(UserRole::SuperAdmin);
    $other = staffUser(UserRole::SuperAdmin);
    $this->actingAs($superAdmin);

    Livewire::test(EditStaffAccount::class, ['record' => $other->getRouteKey()])
        ->fillForm(['is_active' => false, 'password' => '', 'passwordConfirmation' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->refresh()->is_active)->toBeFalse()
        ->and(StaffAccountResource::canEdit($superAdmin))->toBeFalse();

    $this->get(StaffAccountResource::getUrl('edit', ['record' => $superAdmin]))->assertNotFound();
});

test('nobody else can create a super admin, whatever the request says', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(superAdminAccountForm(UserRole::SuperAdmin))
        ->call('create')
        ->assertHasNoErrors();

    expect(User::where('email', staffRunToken().'-super_admin@pest.test')->firstOrFail()->role)
        ->toBe(UserRole::Secretary);
});

test('police admins and officers need an agency that exists, and officers a badge', function () {
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(superAdminAccountForm(UserRole::PoliceAdmin))
        ->call('create')
        ->assertHasFormErrors(['agency_id' => 'required']);

    Livewire::test(CreateStaffAccount::class)
        ->fillForm(superAdminAccountForm(UserRole::LawEnforcement, ['agency_id' => 0]))
        ->call('create')
        ->assertHasFormErrors(['agency_id', 'badge_number' => 'required']);

    expect(User::where('email', 'like', staffRunToken().'-%@pest.test')
        ->whereIn('role', [UserRole::PoliceAdmin->value, UserRole::LawEnforcement->value])
        ->exists())->toBeFalse();
});

test('an admin still creates only secretaries: the role shows greyed out, with no agency or badge field', function () {
    $agency = pestAgency('Admin tamper PD');
    $this->actingAs(staffUser(UserRole::Admin));

    expect(StaffAccountResource::choosesRole())->toBeFalse();

    Livewire::test(CreateStaffAccount::class)
        ->assertFormSet(['role_label' => 'Staff'])
        ->assertFormFieldDisabled('role_label')
        ->assertFormFieldHidden('role')
        ->assertFormFieldHidden('agency_id')
        ->assertFormFieldHidden('badge_number')
        ->fillForm(superAdminAccountForm(UserRole::PoliceAdmin, ['agency_id' => $agency->getKey()]))
        ->call('create')
        ->assertHasNoErrors();

    $user = User::where('email', staffRunToken().'-police_admin@pest.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Secretary)
        ->and($user->lawEnforcementAgent)->toBeNull();
});

test('a super admin lists and opens every role, other super admins included, but never itself', function () {
    $w = pestTwoAgencies();
    $superAdmin = staffUser(UserRole::SuperAdmin);
    $admin = staffUser(UserRole::Admin);
    $secretary = staffUser(UserRole::Secretary);
    $otherSuperAdmin = staffUser(UserRole::SuperAdmin);

    $this->actingAs($superAdmin);

    foreach ([$otherSuperAdmin, $admin, $secretary, $w->policeAdminA, $w->officerB] as $managed) {
        $this->get(StaffAccountResource::getUrl('edit', ['record' => $managed]))->assertOk();
    }

    $this->get(StaffAccountResource::getUrl('edit', ['record' => $superAdmin]))->assertNotFound();

    $listed = StaffAccountResource::getEloquentQuery()->pluck('id');

    expect($listed)->toContain($otherSuperAdmin->getKey(), $admin->getKey(), $secretary->getKey(), $w->policeAdminA->getKey(), $w->officerB->getKey());
});

test('editing an officer saves its badge but never its role or agency', function () {
    $w = pestTwoAgencies();
    $agencyB = $w->officerB->agencyId();
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(EditStaffAccount::class, ['record' => $w->officerA->getRouteKey()])
        ->assertFormSet([
            'role_label' => 'Officer',
            'agency_id' => $w->officerA->agencyId(),
        ])
        ->assertFormFieldDisabled('role_label')
        ->assertFormFieldHidden('role')
        ->assertFormFieldDisabled('agency_id')
        ->fillForm([
            'role' => UserRole::Admin->value,
            'agency_id' => $agencyB,
            'badge_number' => 'NEW-77',
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $officer = $w->officerA->refresh();

    expect($officer->role)->toBe(UserRole::LawEnforcement)
        ->and($officer->agencyId())->not->toBe($agencyB)
        ->and(LawEnforcementAgent::find($officer->getKey())->badge_number)->toBe('NEW-77');
});

test('a super admin sees every agency\'s assessments and every civilian one, but edits none', function () {
    $w = pestTwoAgencies();
    $civilian = pestCivilianAssessment();
    $superAdmin = staffUser(UserRole::SuperAdmin);

    $visible = LawEnforcementAssessment::visibleTo($superAdmin)->pluck('DocumentID');

    expect($visible)->toContain($w->assessmentA->getKey(), $w->assessmentB->getKey())
        ->and(PrivateAssessment::visibleTo($superAdmin)->whereKey($civilian->getKey())->exists())->toBeTrue();

    foreach ([$w->assessmentA, $w->assessmentB] as $assessment) {
        expect(Gate::forUser($superAdmin)->allows('view', $assessment))->toBeTrue()
            ->and(Gate::forUser($superAdmin)->allows('update', $assessment))->toBeFalse()
            ->and(Gate::forUser($superAdmin)->allows('delete', $assessment))->toBeFalse();
    }

    $this->actingAs($superAdmin);

    Livewire::test(ListAssessmentReview::class)
        ->assertCanSeeTableRecords([$w->assessmentA, $w->assessmentB])
        ->assertActionHidden(TestAction::make('edit')->table($w->assessmentA));

    cleanupCivilianAssessments();
});

test('a super admin works every follow-up kind and hears about every new submission', function () {
    $superAdmin = staffUser(UserRole::SuperAdmin);

    expect(FollowUp::kindsFor($superAdmin))->toBe(FollowUpKind::cases());

    foreach (App\Enums\NewSubmission::cases() as $submission) {
        expect($submission->alertedRoles())->toContain(UserRole::SuperAdmin);
    }
});

test('only an active super admin opens Agencies', function () {
    $cases = [
        'super admin' => [staffUser(UserRole::SuperAdmin), true],
        'inactive super admin' => [staffUser(UserRole::SuperAdmin, isActive: false), false],
        'admin' => [staffUser(UserRole::Admin), false],
        'secretary' => [staffUser(UserRole::Secretary), false],
        'police admin' => [pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Agencies PD')), false],
        'officer' => [staffUser(UserRole::LawEnforcement), false],
    ];

    foreach ($cases as $label => [$user, $expected]) {
        $this->actingAs($user);

        expect(AgencyResource::canAccess())->toBe($expected, "Agencies for: {$label}")
            ->and(AgencyResource::canCreate())->toBe($expected, "adding agencies for: {$label}");
    }

    $this->actingAs(staffUser(UserRole::Admin))
        ->get(AgencyResource::getUrl())
        ->assertForbidden();
});

test('a super admin adds and renames agencies, never with a duplicate name, and never deletes one', function () {
    $existing = pestAgency('Existing PD');
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(ManageAgencies::class)
        ->callAction(CreateAction::class, ['name' => '  '.staffRunToken().' New PD  '])
        ->assertHasNoFormErrors();

    $agency = Agency::where('name', staffRunToken().' New PD')->firstOrFail();

    Livewire::test(ManageAgencies::class)
        ->callAction(CreateAction::class, ['name' => $existing->name])
        ->assertHasFormErrors(['name' => "\"{$existing->name}\" is already on the list."]);

    Livewire::test(ManageAgencies::class)
        ->callAction(TestAction::make(EditAction::class)->table($agency), ['name' => staffRunToken().' Renamed PD'])
        ->assertHasNoFormErrors()
        ->assertActionDoesNotExist(TestAction::make('delete')->table($agency));

    expect($agency->refresh()->name)->toBe(staffRunToken().' Renamed PD')
        ->and(AgencyResource::canDelete($agency))->toBeFalse();
});

test('the agencies list counts each agency\'s police admins and officers', function () {
    $w = pestTwoAgencies();
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    $agency = AgencyResource::getEloquentQuery()->whereKey($w->officerA->agencyId())->sole();

    expect($agency->police_admins_count)->toBe(1)
        ->and($agency->officers_count)->toBe(1);
});

test('the command creates an active super admin and never touches an existing account', function () {
    $email = staffRunToken().'-cli@pest.test';

    $this->artisan('staff:create-super-admin', ['email' => $email, '--first-name' => 'Casey', '--last-name' => 'Lee'])
        ->expectsQuestion(CreateSuperAdmin::PASSWORD_PROMPT, SUPER_ADMIN_TEST_PASSWORD)
        ->expectsQuestion('Password again', SUPER_ADMIN_TEST_PASSWORD)
        ->assertSuccessful();

    $user = User::where('email', $email)->firstOrFail();

    expect($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->is_active)->toBeTrue()
        ->and($user->name)->toBe('Casey Lee');

    $secretary = staffUser(UserRole::Secretary);

    $this->artisan('staff:create-super-admin', ['email' => $secretary->email, '--first-name' => 'Casey', '--last-name' => 'Lee'])
        ->expectsQuestion(CreateSuperAdmin::PASSWORD_PROMPT, SUPER_ADMIN_TEST_PASSWORD)
        ->expectsQuestion('Password again', SUPER_ADMIN_TEST_PASSWORD)
        ->assertFailed();

    expect($secretary->refresh()->role)->toBe(UserRole::Secretary);

    $this->artisan('staff:create-super-admin', ['email' => staffRunToken().'-short@pest.test', '--first-name' => 'Casey', '--last-name' => 'Lee'])
        ->expectsQuestion(CreateSuperAdmin::PASSWORD_PROMPT, 'short')
        ->expectsQuestion('Password again', 'short')
        ->assertFailed();

    expect(User::where('email', staffRunToken().'-short@pest.test')->exists())->toBeFalse();
});
