<?php

use App\Models\ContentFile;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Newsletter;
use Illuminate\Http\Testing\File as TestingFile;

/*
GET /api/public/events and /api/public/newsletters - the feed the public
website renders.

THIS FILE IS THE CONTRACT. The Events & News page was built against
website/frontend/src/app/lib/mock-events.json and mock-newsletters.json; those
files were the original specification and were deleted once these endpoints
matched them, so the expected key structure below is now the only written
record of it. Changing a key here means changing
website/frontend/src/app/lib/content.js and its consumers in the same commit.

Reminder on cleanup: no RefreshDatabase, same as the rest of the suite. These
tests write to the real Content database and sweep up afterwards by the
`peststaff` prefix (cleanupStaffData()).
*/

afterEach(fn () => cleanupStaffData());

// Exactly the keys mock-events.json carried, in its order.
const EVENT_KEYS = [
    'id', 'title', 'summary', 'description', 'startsAt', 'endsAt', 'allDay',
    'recurrence', 'location', 'image', 'registration', 'category',
    'isPublished', 'isCancelled',
];

const NEWSLETTER_KEYS = ['id', 'title', 'issueDate', 'summary', 'file', 'isPublished'];

function pestPng(): TestingFile
{
    $resource = tmpfile();
    fwrite($resource, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    return new TestingFile(staffRunToken().'-api.png', $resource);
}

test('the events endpoint serves published events in the contracted shape', function () {
    $category = EventCategory::create(['Name' => staffRunToken().' Support Group']);

    Event::create([
        'title' => staffRunToken().' Moonlight Walk',
        'summary' => 'An evening walk.',
        'description' => ['First paragraph.', 'Second paragraph.'],
        // 18:00 Eastern in October is -04:00, so 22:00 UTC.
        'starts_at' => '2026-10-22 22:00:00',
        'ends_at' => '2026-10-23 00:00:00',
        'all_day' => false,
        'recurrence' => 'Annually in October',
        'location_name' => 'Johnston Park',
        'location_address' => 'Cleveland, TN 37311',
        'location_is_virtual' => false,
        'registration_url' => 'https://example.com/register',
        'registration_label' => 'Reserve a seat',
        'category_id' => $category->id,
        'is_published' => true,
    ]);

    $response = $this->getJson('/api/public/events')->assertOk();

    $event = collect($response->json('data'))
        ->firstWhere('title', staffRunToken().' Moonlight Walk');

    expect($event)->not->toBeNull()
        ->and(array_keys($event))->toBe(EVENT_KEYS);

    expect($event['summary'])->toBe('An evening walk.')
        ->and($event['description'])->toBe(['First paragraph.', 'Second paragraph.'])
        ->and($event['allDay'])->toBeFalse()
        ->and($event['recurrence'])->toBe('Annually in October')
        ->and($event['category'])->toBe(staffRunToken().' Support Group')
        ->and($event['isPublished'])->toBeTrue()
        ->and($event['isCancelled'])->toBeFalse()
        ->and($event['image'])->toBeNull();

    // Times are emitted in Eastern with an offset, matching the mock, not as
    // a UTC "Z" string.
    expect($event['startsAt'])->toBe('2026-10-22T18:00:00-04:00')
        ->and($event['endsAt'])->toBe('2026-10-22T20:00:00-04:00');

    // Nested objects are recomposed from the flattened columns.
    expect($event['location'])->toBe([
        'name' => 'Johnston Park',
        'address' => 'Cleveland, TN 37311',
        'isVirtual' => false,
    ]);

    expect($event['registration'])->toBe([
        'url' => 'https://example.com/register',
        'label' => 'Reserve a seat',
    ]);
});

test('an unpublished event is not served', function () {
    Event::create([
        'title' => staffRunToken().' Secret draft',
        'starts_at' => now()->addMonth(),
        'is_published' => false,
    ]);

    $titles = collect($this->getJson('/api/public/events')->json('data'))->pluck('title');

    expect($titles)->not->toContain(staffRunToken().' Secret draft');
});

test('a virtual event carries a joining note and no address', function () {
    Event::create([
        'title' => staffRunToken().' Online session',
        'starts_at' => now()->addMonth(),
        'location_name' => 'Online',
        'location_is_virtual' => true,
        'location_virtual_note' => 'A link is emailed the morning of the session.',
        'is_published' => true,
    ]);

    $event = collect($this->getJson('/api/public/events')->json('data'))
        ->firstWhere('title', staffRunToken().' Online session');

    // Matches the mock's virtual-location shape: no address key at all.
    expect($event['location'])->toBe([
        'name' => 'Online',
        'isVirtual' => true,
        'virtualNote' => 'A link is emailed the morning of the session.',
    ]);
});

test('an event with no location at all emits null, not an empty object', function () {
    // The page guards on truthiness, and {name: null} is truthy - it would
    // render an empty row with a map pin.
    Event::create([
        'title' => staffRunToken().' Nowhere',
        'starts_at' => now()->addMonth(),
        'is_published' => true,
    ]);

    $event = collect($this->getJson('/api/public/events')->json('data'))
        ->firstWhere('title', staffRunToken().' Nowhere');

    expect($event['location'])->toBeNull()
        ->and($event['registration'])->toBeNull()
        ->and($event['image'])->toBeNull()
        // Null in the column, but the contract says array.
        ->and($event['description'])->toBe([]);
});

test('an event image is served as an absolute, reachable URL', function () {
    $file = ContentFile::storeUpload(pestPng());

    Event::create([
        'title' => staffRunToken().' With picture',
        'starts_at' => now()->addMonth(),
        'image_file_id' => $file->getKey(),
        'image_alt' => 'A lighthouse at sunset',
        'is_published' => true,
    ]);

    $event = collect($this->getJson('/api/public/events')->json('data'))
        ->firstWhere('title', staffRunToken().' With picture');

    expect($event['image']['alt'])->toBe('A lighthouse at sunset')
        // Absolute: the website is served from a different origin.
        ->and($event['image']['src'])->toStartWith('http');

    // And it actually resolves, which is what the site's <img> will do.
    $this->get($event['image']['src'])
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('events are ordered soonest first', function () {
    foreach ([3, 1, 2] as $monthsOut) {
        Event::create([
            'title' => staffRunToken()." Event {$monthsOut}",
            'starts_at' => now()->addMonths($monthsOut),
            'is_published' => true,
        ]);
    }

    $mine = collect($this->getJson('/api/public/events')->json('data'))
        ->pluck('title')
        ->filter(fn (string $t): bool => str_starts_with($t, staffRunToken()))
        ->values()
        ->all();

    expect($mine)->toBe([
        staffRunToken().' Event 1',
        staffRunToken().' Event 2',
        staffRunToken().' Event 3',
    ]);
});

test('the newsletters endpoint serves published issues in the contracted shape', function () {
    $resource = tmpfile();
    fwrite($resource, '%PDF-1.4'.str_repeat('a', 2000));
    $pdf = new TestingFile(staffRunToken().'-issue.pdf', $resource);

    $file = ContentFile::storeUpload($pdf);

    Newsletter::create([
        'title' => staffRunToken().' Autumn',
        'issue_date' => '2026-09-01',
        'summary' => 'What happened this quarter.',
        'file_id' => $file->getKey(),
        'file_pages' => 8,
        'is_published' => true,
    ]);

    $response = $this->getJson('/api/public/newsletters')->assertOk();

    $issue = collect($response->json('data'))
        ->firstWhere('title', staffRunToken().' Autumn');

    expect($issue)->not->toBeNull()
        ->and(array_keys($issue))->toBe(NEWSLETTER_KEYS);

    expect($issue['summary'])->toBe('What happened this quarter.')
        // A calendar date, not a timestamp - the site formats it in UTC so a
        // month label cannot slip backwards.
        ->and($issue['issueDate'])->toBe('2026-09-01')
        ->and($issue['isPublished'])->toBeTrue();

    expect($issue['file']['pages'])->toBe(8)
        ->and($issue['file']['sizeBytes'])->toBe($file->size_bytes)
        ->and($issue['file']['url'])->toStartWith('http');
});

test('a newsletter with no file attached emits a null file', function () {
    Newsletter::create([
        'title' => staffRunToken().' No PDF yet',
        'issue_date' => '2026-08-01',
        'is_published' => true,
    ]);

    $issue = collect($this->getJson('/api/public/newsletters')->json('data'))
        ->firstWhere('title', staffRunToken().' No PDF yet');

    // The site reads issue.file?.url ?? "#", so null is handled.
    expect($issue['file'])->toBeNull();
});

test('newsletters are ordered newest issue first', function () {
    foreach (['2026-01-01', '2026-07-01', '2026-04-01'] as $date) {
        Newsletter::create([
            'title' => staffRunToken().' '.$date,
            'issue_date' => $date,
            'is_published' => true,
        ]);
    }

    $mine = collect($this->getJson('/api/public/newsletters')->json('data'))
        ->pluck('title')
        ->filter(fn (string $t): bool => str_starts_with($t, staffRunToken()))
        ->values()
        ->all();

    expect($mine)->toBe([
        staffRunToken().' 2026-07-01',
        staffRunToken().' 2026-04-01',
        staffRunToken().' 2026-01-01',
    ]);
});

test('an unpublished newsletter is not served', function () {
    Newsletter::create([
        'title' => staffRunToken().' Draft issue',
        'issue_date' => '2026-06-01',
        'is_published' => false,
    ]);

    $titles = collect($this->getJson('/api/public/newsletters')->json('data'))->pluck('title');

    expect($titles)->not->toContain(staffRunToken().' Draft issue');
});

test('both endpoints are reachable without authentication', function () {
    // They are the public website's only source of content, and the site has
    // no credentials of any kind.
    $this->getJson('/api/public/events')->assertOk();
    $this->getJson('/api/public/newsletters')->assertOk();
});

test('the endpoints return an empty list rather than failing when there is no content', function () {
    // Which is the state the site launches in, before staff publish anything.
    expect($this->getJson('/api/public/events')->assertOk()->json('data'))->toBeArray()
        ->and($this->getJson('/api/public/newsletters')->assertOk()->json('data'))->toBeArray();
});
