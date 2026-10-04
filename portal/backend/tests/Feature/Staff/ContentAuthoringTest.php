<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\CreateEvent;
use App\Filament\Clusters\Content\Resources\Events\Pages\EditEvent;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Models\ContentFile;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Newsletter;
use Illuminate\Http\Testing\File as TestingFile;
use Livewire\Livewire;

/*
The two things the panel was asked for: creating an event and uploading a
newsletter. Driven through Livewire rather than asserted on the form
definition, because the parts most likely to break are the conversions that
only happen on submit - the paragraph array, the timezone, and the upload
becoming a content_files row.
*/

afterEach(fn () => cleanupStaffData());

/*
Real bytes on disk, not UploadedFile::fake().

fake()->image() needs the GD extension, which this project does not require
and the dev box does not have. And fake()->create() reports a size while
writing nothing at all, so a file stored from it lands in the database as a
zero-byte row and every size assertion is meaningless. Both of those cost a
debugging session once already.
*/
function pestUploadWithBytes(string $name, string $bytes): TestingFile
{
    // Illuminate\Http\Testing\File specifically, not a plain UploadedFile:
    // Livewire's test upload helper reads $file->name, a public property only
    // this subclass has, and a plain UploadedFile dies with "Undefined
    // property". Its constructor takes an open resource, which is also how the
    // real bytes get in.
    $resource = tmpfile();
    fwrite($resource, $bytes);

    return new TestingFile($name, $resource);
}

function pestImageUpload(): TestingFile
{
    // A real 1x1 PNG, so getMimeType()'s content sniffing returns image/png.
    return pestUploadWithBytes(
        staffRunToken().'-event.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
    );
}

// 128 KB of real PDF-ish bytes, so the stored size is assertable.
function pestPdfUpload(): TestingFile
{
    return pestUploadWithBytes(
        staffRunToken().'-issue.pdf',
        '%PDF-1.4'.str_repeat('a', 128 * 1024 - 8),
    );
}

test('a secretary can create an event', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $category = EventCategory::create(['Name' => staffRunToken().' Fundraiser']);
    $title = staffRunToken().' Autumn fundraiser';

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => $title,
            'summary' => 'An evening of fundraising.',
            'description' => "First paragraph.\n\nSecond paragraph.\n\nThird.",
            'category_id' => $category->id,
            // Eastern wall-clock, as a staff member would type it.
            'starts_at' => '2027-03-20 17:30',
            'ends_at' => '2027-03-20 20:00',
            'location_name' => 'Community Hall',
            'location_address' => '1 Example Street',
            'registration_url' => 'https://example.com/register',
            'registration_label' => 'Register now',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::where('title', $title)->firstOrFail();

    expect($event->is_published)->toBeTrue()
        ->and($event->is_cancelled)->toBeFalse()
        ->and($event->category_id)->toBe($category->id)
        // The textarea became the paragraph array the website maps over.
        ->and($event->description)->toBe([
            'First paragraph.',
            'Second paragraph.',
            'Third.',
        ]);

    /*
    The timezone round trip, which is the one most likely to be silently wrong.
    17:30 Eastern on 2027-03-20 is after that year's DST switch, so the offset
    is -04:00 and the stored UTC value must be 21:30 - not 22:30, which is what
    a hard-coded -05:00 would produce.
    */
    expect($event->starts_at->toDateTimeString())->toBe('2027-03-20 21:30:00')
        ->and($event->starts_at->timezone(Event::DISPLAY_TIMEZONE)->format('H:i'))->toBe('17:30');
});

test('an event date on the other side of the DST boundary also round-trips', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $title = staffRunToken().' Winter meeting';

    // January: Eastern is -05:00, so 17:30 local is 22:30 UTC.
    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => $title,
            'starts_at' => '2027-01-15 17:30',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::where('title', $title)->firstOrFail();

    expect($event->starts_at->toDateTimeString())->toBe('2027-01-15 22:30:00')
        ->and($event->starts_at->timezone(Event::DISPLAY_TIMEZONE)->format('H:i'))->toBe('17:30');
});

test('an event image is stored in the database and reachable once published', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $title = staffRunToken().' Event with a picture';

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => $title,
            'starts_at' => '2027-05-01 10:00',
            'image_file_id' => pestImageUpload(),
            'image_alt' => 'People at a fundraiser',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::where('title', $title)->firstOrFail();

    expect($event->image_file_id)->not->toBeNull()
        // Nothing was written to a disk - the fallback column stays empty.
        ->and($event->image_path)->toBeNull();

    $file = ContentFile::find($event->image_file_id);

    expect($file)->not->toBeNull()
        ->and($file->mime_type)->toBe('image/png')
        ->and($file->size_bytes)->toBeGreaterThan(0)
        ->and(strlen($file->contents()))->toBe($file->size_bytes);

    // And it is actually served to the public site.
    $this->get($event->imageUrl())
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('an image description is required once an image is attached', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // Accessibility rule from the form: an image without alt text is rejected
    // rather than quietly published.
    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => staffRunToken().' Missing alt text',
            'starts_at' => '2027-05-01 10:00',
            'image_file_id' => pestImageUpload(),
            'image_alt' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['image_alt']);
});

test('an event edit loads its paragraphs back into the textarea', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $event = Event::create([
        'title' => staffRunToken().' Editable',
        'description' => ['One.', 'Two.'],
        'starts_at' => now()->addWeek(),
        'is_published' => false,
    ]);

    Livewire::test(EditEvent::class, ['record' => $event->getKey()])
        // The json array is shown as blank-line separated text, not as JSON.
        ->assertFormSet(['description' => "One.\n\nTwo."]);
});

test('editing an event keeps its paragraphs intact', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $event = Event::create([
        'title' => staffRunToken().' Round trip',
        'description' => ['One.', 'Two.'],
        'starts_at' => now()->addWeek(),
        'is_published' => false,
    ]);

    Livewire::test(EditEvent::class, ['record' => $event->getKey()])
        ->fillForm(['description' => "One.\n\nTwo.\n\nThree."])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->description)->toBe(['One.', 'Two.', 'Three.']);
});

test('an empty description is stored as null, not an empty array', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // The website guards on truthiness, and [''] is truthy - it would render
    // an empty paragraph.
    $title = staffRunToken().' No description';

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => $title,
            'starts_at' => '2027-06-01 12:00',
            'description' => '   ',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::where('title', $title)->firstOrFail()->description)->toBeNull();
});

test('a secretary can upload a newsletter and its size is recorded', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $title = staffRunToken().' Autumn issue';
    $pdf = pestPdfUpload();

    Livewire::test(ManageNewsletters::class)
        ->callAction('create', data: [
            'title' => $title,
            'issue_date' => '2026-09-01',
            'summary' => 'What happened this quarter.',
            'file_id' => $pdf,
            'file_pages' => 8,
            'is_published' => true,
        ])
        ->assertHasNoActionErrors();

    $newsletter = Newsletter::where('title', $title)->firstOrFail();

    expect($newsletter->file_id)->not->toBeNull()
        ->and($newsletter->file_path)->toBeNull()
        ->and($newsletter->file_pages)->toBe(8)
        ->and($newsletter->hasFile())->toBeTrue();

    $file = ContentFile::find($newsletter->file_id);

    expect($file->size_bytes)->toBe(128 * 1024)
        // Filled in from the stored row rather than typed, so the two cannot
        // disagree.
        ->and($newsletter->file_size_bytes)->toBe($file->size_bytes);

    $this->get($newsletter->fileUrl())->assertOk();
});

test('an event is rejected without a title or a start time', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(CreateEvent::class)
        ->fillForm(['title' => null, 'starts_at' => null])
        ->call('create')
        ->assertHasFormErrors(['title', 'starts_at']);
});

test('an end time before the start is rejected', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => staffRunToken().' Backwards',
            'starts_at' => '2027-05-01 18:00',
            'ends_at' => '2027-05-01 09:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_at']);
});

test('an officer cannot reach the event create page at all', function () {
    $this->actingAs(staffUser(UserRole::LawEnforcement));

    // Belt and braces alongside the HTTP-level test: the Livewire component
    // itself must refuse, not just the route.
    Livewire::test(CreateEvent::class)->assertForbidden();
});
