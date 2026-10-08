<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Models\ContentFile;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Newsletter;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
Every content-management page actually renders, with a row in it.

canAccess() tests prove the gating; they do not prove the page works. These
tables cross connections - events and newsletters live on `Content`, the
submissions and lookups on `Feedback`, assessments on `Portal` - and the usual
failure is a relationship or filter that Filament resolves on the default
(unreachable) connection. That throws at render time and nowhere else, so the
only way to catch it is to render the page.

Each page is loaded with at least one record present, because an empty table
renders a very different path and would hide exactly the bug being looked for.
*/

afterEach(fn () => cleanupStaffData());

function seedContentRow(): Event
{
    $category = EventCategory::create(['Name' => staffRunToken().' Workshop']);

    $file = ContentFile::storeUpload(
        new Illuminate\Http\UploadedFile(
            tap(tempnam(sys_get_temp_dir(), 'pest'), fn ($p) => file_put_contents(
                $p,
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            )),
            staffRunToken().'-render.png',
            'image/png',
            test: true,
        ),
    );

    return Event::create([
        'title' => staffRunToken().' Community meeting',
        'summary' => 'A test event.',
        'description' => ['First paragraph.', 'Second paragraph.'],
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(2),
        'category_id' => $category->id,
        'image_file_id' => $file->getKey(),
        'image_alt' => 'A test image',
        'location_name' => 'Community Hall',
        'is_published' => true,
    ]);
}

test('the content pages render for a secretary', function (string $route) {
    seedContentRow();

    Newsletter::create([
        'title' => staffRunToken().' Autumn',
        'issue_date' => '2026-09-01',
        'is_published' => true,
    ]);

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(route($route))
        ->assertOk();
})->with([
    'events' => 'filament.staff.content.resources.events.index',
    'newsletters' => 'filament.staff.content.resources.newsletters.index',
    'categories' => 'filament.staff.content.resources.categories.index',
    'service feedback' => 'filament.staff.submissions.resources.service-feedback.index',
    'resource requests' => 'filament.staff.submissions.resources.resource-requests.index',
    'services' => 'filament.staff.form-options.resources.services.index',
    'resource types' => 'filament.staff.form-options.resources.resource-types.index',
    'counties' => 'filament.staff.form-options.resources.counties.index',
]);

/*
One actingAs() per test, deliberately. The panel's middleware stack includes
AuthenticateSession, which invalidates the session when the authenticated user
changes - so signing in as a second user mid-test gets redirected to the login
screen rather than serving the page.
*/
// Events are created and edited in pop-ups on the list page, so these open the
// pop-up rather than visiting a page of their own.
test('the event create form renders', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ManageEvents::class)
        ->mountAction('create')
        ->assertActionMounted('create')
        ->assertOk();
});

test('the event edit form renders with an existing record', function () {
    // This is where the description json-to-textarea conversion and the
    // uuid-valued file upload are exercised.
    $event = seedContentRow();

    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ManageEvents::class)
        ->mountAction(TestAction::make(EditAction::class)->table($event))
        ->assertActionMounted(TestAction::make(EditAction::class)->table($event))
        ->assertOk()
        ->assertSee(staffRunToken().' Community meeting');
});

test('the assessment review page renders for an admin and a police admin', function (UserRole $role) {
    $this->actingAs(staffUser($role))
        ->get(route('filament.staff.resources.assessment-review.index'))
        ->assertOk();
})->with([
    'admin' => [UserRole::Admin],
    'police_admin' => [UserRole::PoliceAdmin],
]);

test('a secretary is refused the assessment review page', function () {
    // Section 5 rule 3: secretary never sees assessment PII. Asserted against
    // the real HTTP response, not just canAccess().
    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(route('filament.staff.resources.assessment-review.index'))
        ->assertForbidden();
});

test('an officer is refused every content page', function (string $route) {
    $this->actingAs(staffUser(UserRole::LawEnforcement))
        ->get(route($route))
        ->assertForbidden();
})->with([
    'events' => 'filament.staff.content.resources.events.index',
    'submissions' => 'filament.staff.submissions.resources.resource-requests.index',
    'form options' => 'filament.staff.form-options.resources.services.index',
]);

/*
The landing behaviour the whole change was about: signing in puts admin and
secretary on content management, officers on the officer portal, and the two
are different addresses.
*/
test('opening the panel root sends each role to its own area', function (UserRole $role, string $landing) {
    $this->actingAs(staffUser($role))
        ->get('/')
        ->assertRedirect(route($landing));
})->with([
    'secretary to content' => [UserRole::Secretary, 'filament.staff.content'],
    'admin to content' => [UserRole::Admin, 'filament.staff.content'],
    'officer to the officer portal' => [UserRole::LawEnforcement, 'filament.staff.pages.police'],
    'police admin to the officer portal' => [UserRole::PoliceAdmin, 'filament.staff.pages.police'],
]);

/*
Sign-in has one neutral address shared by every role - /login, at the site
root - rather than living under the content or the police side. An anonymous
visit to / goes there first.
*/
test('sign-in is at /login, and an anonymous visit to / is sent there', function () {
    expect(filament()->getPanel('staff')->getLoginUrl())->toBe(url('/login'));

    $this->get('/')->assertRedirect(url('/login'));
    $this->get('/login')->assertOk();

    // The panel used to live under /staff; old links still arrive.
    $this->get('/staff/login')->assertRedirect(url('/login'));
    $this->get('/staff/police/assessments')->assertRedirect(url('/police/assessments'));
});

test('the content cluster root forwards to the first tab the user can open', function () {
    // Cluster::mount() does this, so /content is never a dead end.
    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(route('filament.staff.content'))
        ->assertRedirect(route('filament.staff.content.resources.events.index'));
});

test('an inactive admin is refused the content pages over HTTP', function () {
    $this->actingAs(staffUser(UserRole::Admin, isActive: false))
        ->get(route('filament.staff.content.resources.events.index'))
        ->assertForbidden();
});
