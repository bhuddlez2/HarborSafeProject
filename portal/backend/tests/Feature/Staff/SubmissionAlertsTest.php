<?php

use App\Enums\NewSubmission;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Pages\NewAssessment;
use App\Livewire\StaffNotifications;
use App\Models\StaffNotification;
use App\Models\User;
use App\Notifications\NewSubmissionAlert;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
The staff panel's bell (team plan task 17): who is alerted about which new
submission (App\Enums\NewSubmission), and how the bell behaves.

Alerts are faked by default in every test (tests/TestCase.php), so only test
users are asserted on. The tests that need real rows build them directly, or
swap the real dispatcher back in.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// One of each kind of staff account, so each test can check exactly who heard.
function alertAudience(): object
{
    return (object) [
        'admin' => staffUser(UserRole::Admin),
        'secretary' => staffUser(UserRole::Secretary),
        'policeAdmin' => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Alerts PD')),
        'officer' => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Alerts PD 2')),
        'inactiveAdmin' => staffUser(UserRole::Admin, isActive: false),
        'inactiveSecretary' => staffUser(UserRole::Secretary, isActive: false),
    ];
}

function assertAlerted(object $people, NewSubmission $submission, array $alerted): void
{
    foreach ((array) $people as $key => $user) {
        $match = fn (NewSubmissionAlert $alert): bool => $alert->submission === $submission;

        in_array($key, $alerted, true)
            ? Notification::assertSentTo($user, NewSubmissionAlert::class, $match)
            : Notification::assertNotSentTo($user, NewSubmissionAlert::class, $match);
    }
}

// A bell alert as the database channel would store it, read or not.
function bellAlert(User $user, string $title, ?Carbon\CarbonInterface $readAt = null): StaffNotification
{
    return StaffNotification::forceCreate([
        'id' => (string) Str::uuid(),
        'type' => NewSubmissionAlert::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->getKey(),
        'data' => ['format' => 'filament', 'title' => $title, 'body' => null, 'actions' => [], 'duration' => 'persistent', 'icon' => null, 'iconColor' => null, 'status' => null, 'view' => null, 'viewData' => []],
        'read_at' => $readAt,
    ]);
}

test('a resource request from the website alerts secretaries and admins, nobody else', function () {
    $people = alertAudience();

    $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertCreated();

    assertAlerted($people, NewSubmission::ResourceRequest, ['admin', 'secretary']);
});

test('service feedback from the website alerts secretaries and admins, nobody else', function () {
    $people = alertAudience();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 4,
    ])->assertCreated();

    assertAlerted($people, NewSubmission::ServiceFeedback, ['admin', 'secretary']);
});

test('feedback staff enter themselves in the panel alerts nobody', function () {
    alertAudience();
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ListServiceFeedback::class)
        ->callAction('create', data: [
            'ServiceID' => publicFormsMakeService(),
            'Rating' => 3,
            'SubmissionDate' => '2026-10-01 10:00',
        ])
        ->assertHasNoActionErrors();

    Notification::assertNothingSent();
});

test('a civilian assessment from the website alerts admins only', function () {
    $people = alertAudience();

    $this->postJson('/api/public/civilian-assessments', [
        'ForWhom' => 'self',
        ...collect(range(1, 11))->mapWithKeys(fn (int $n): array => ["RiskIndicator{$n}" => false])->all(),
        'VictimFirstName' => STAFF_TEST_PREFIX.'Victim',
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        'VictimSafePhoneNumber' => '(423)555-0100',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
    ])->assertCreated();

    assertAlerted($people, NewSubmission::CivilianAssessment, ['admin']);
});

test('an officer\'s assessment alerts admins only, not the officer or their police admin', function () {
    $people = alertAudience();
    $this->actingAs($people->officer);

    Livewire::test(NewAssessment::class)
        ->fillForm([
            ...collect(range(1, 11))->mapWithKeys(fn (int $n): array => ["RiskIndicator{$n}" => $n === 1])->all(),
            'VictimFirstName' => 'Test',
            'VictimLastName' => 'Victim',
            'VictimDOB' => '1990-01-01',
            'VictimSex' => 'F',
            'VictimSafePhoneNumber' => '(423)555-0100',
            'OffenderFirstName' => 'Test',
            'OffenderLastName' => 'Offender',
            'OffenderSex' => 'M',
            'OffenderVictimRelationship' => 'spouse',
        ])
        ->call('create')
        ->assertSet('submitted', true);

    assertAlerted($people, NewSubmission::LawEnforcementAssessment, ['admin']);
});

test('a failed alert never stops a submission', function () {
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('alerts are down'));

    $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertCreated();

    Exceptions::assertReported(RuntimeException::class);
});

test('an alert is stored on Portal in the bell\'s format, with a link to its list', function () {
    Notification::swap(new ChannelManager(app()));
    $admin = staffUser(UserRole::Admin);

    $admin->notify(new NewSubmissionAlert(NewSubmission::CivilianAssessment));

    $alert = $admin->notifications()->sole();

    expect($alert)->toBeInstanceOf(StaffNotification::class)
        ->and($alert->getConnectionName())->toBe('Portal')
        ->and($alert->read_at)->toBeNull()
        ->and($alert->data['format'])->toBe('filament')
        ->and($alert->data['title'])->toBe('New civilian assessment')
        ->and($alert->data['actions'][0]['url'])->toBe('/civilian-assessments');
});

test('the bell is in the sidebar for secretaries and admins, and nobody else', function (Closure $makeUser, bool $hasBell) {
    $page = $this->actingAs($makeUser())->followingRedirects()->get('/')->assertOk();

    $hasBell
        ? $page->assertSee('fi-sidebar-database-notifications-btn', false)->assertSee('READ_AFTER_MS', false)
        : $page->assertDontSee('fi-sidebar-database-notifications-btn', false)->assertDontSee('READ_AFTER_MS', false);

    // Never polled: a poll would keep an idle session alive (SessionTimeoutTest).
    $page->assertDontSee('wire:poll', false);
})->with([
    'admin' => [fn () => staffUser(UserRole::Admin), true],
    'secretary' => [fn () => staffUser(UserRole::Secretary), true],
    'police admin' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Bell PD')), false],
    'officer' => [fn () => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Bell PD 2')), false],
]);

test('the bell counts unread alerts, and keeps read ones listed for 12 hours', function () {
    $admin = staffUser(UserRole::Admin);
    bellAlert($admin, 'Unread');
    bellAlert($admin, 'Read an hour ago', now()->subHour());
    bellAlert($admin, 'Read 13 hours ago', now()->subHours(13));

    $this->actingAs($admin);
    $bell = Livewire::test(StaffNotifications::class)->instance();

    expect($bell->getUnreadNotificationsCount())->toBe(1)
        ->and($bell->getNotifications()->pluck('data.title')->all())
        ->toEqualCanonicalizing(['Unread', 'Read an hour ago']);
});

test('reading the alerts clears the count but keeps them in the list', function () {
    $admin = staffUser(UserRole::Admin);
    bellAlert($admin, 'First');
    bellAlert($admin, 'Second');

    $this->actingAs($admin);

    // What the page script calls once the panel has been open ~5 seconds.
    $bell = Livewire::test(StaffNotifications::class)
        ->call('markAllNotificationsAsRead')
        ->instance();

    expect($bell->getUnreadNotificationsCount())->toBe(0)
        ->and($bell->getNotifications())->toHaveCount(2);
});

test('read alerts older than 12 hours are pruned, unread ones never', function () {
    $admin = staffUser(UserRole::Admin);
    $old = bellAlert($admin, 'Old', now()->subHours(13));
    $recent = bellAlert($admin, 'Recent', now()->subHour());
    $unread = bellAlert($admin, 'Unread');

    $prunable = (new StaffNotification)->prunable()->pluck('id')->all();

    expect($prunable)->toContain($old->getKey())
        ->not->toContain($recent->getKey())
        ->not->toContain($unread->getKey());
});
