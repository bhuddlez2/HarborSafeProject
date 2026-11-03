<?php

use App\Enums\UserRole;
use App\Models\ContentFile;
use App\Models\Event;
use App\Models\Newsletter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/*
Event images and newsletter PDFs stored as rows rather than files.

The things worth testing here are the ones that would fail silently:

  - the blob surviving a round trip byte for byte,
  - the global scope actually keeping `contents` out of ordinary queries,
    which is the difference between a listing page costing kilobytes and
    costing hundreds of megabytes,
  - a draft's file being unreachable publicly, since that is the whole of the
    endpoint's authorization.
*/

afterEach(fn () => cleanupStaffData());

function pestPngBytes(): string
{
    // A real 1x1 PNG, so getMimeType()'s content sniffing returns image/png
    // rather than guessing from the extension.
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

function pestUpload(string $suffix = '.png', ?string $bytes = null): UploadedFile
{
    $bytes ??= pestPngBytes();
    $path = tempnam(sys_get_temp_dir(), 'pest');
    file_put_contents($path, $bytes);

    return new UploadedFile(
        $path,
        staffRunToken().'-image'.$suffix,
        $suffix === '.pdf' ? 'application/pdf' : 'image/png',
        test: true,
    );
}

test('an uploaded file round-trips through the database byte for byte', function () {
    $bytes = pestPngBytes();

    $record = ContentFile::storeUpload(pestUpload());

    expect($record->exists)->toBeTrue()
        ->and($record->getKey())->not->toBeEmpty()
        ->and($record->size_bytes)->toBe(strlen($bytes))
        ->and($record->mime_type)->toBe('image/png')
        ->and($record->checksum)->toBe(hash('sha256', $bytes));

    // Re-read from the database rather than trusting the in-memory instance.
    $reloaded = ContentFile::find($record->getKey());

    expect($reloaded->contents())->toBe($bytes)
        ->and($reloaded->size_bytes)->toBe(strlen($bytes));
});

test('a larger binary payload survives intact', function () {
    // 256 KB of non-text bytes, covering the full 0-255 range - enough to
    // catch an encoding or charset problem that a tiny PNG would not.
    $bytes = str_repeat(implode('', array_map('chr', range(0, 255))), 1024);

    $record = ContentFile::storeUpload(pestUpload('.png', $bytes));

    expect(strlen($bytes))->toBe(262144)
        ->and(ContentFile::find($record->getKey())->contents())->toBe($bytes);
});

/*
The performance guarantee, asserted rather than assumed. If someone removes
the global scope, this is what catches it - and it would not show up as a
visible bug until a listing page timed out in production.
*/
test('ordinary queries never load the blob', function () {
    $record = ContentFile::storeUpload(pestUpload());

    $fetched = ContentFile::find($record->getKey());

    expect($fetched->getAttributes())->not->toHaveKey('contents')
        ->and(ContentFile::query()->get()->first()?->getAttributes())->not->toHaveKey('contents');

    // And the generated SQL names its columns rather than selecting *.
    expect(ContentFile::query()->toSql())
        ->toContain('content_files`.`name')
        ->not->toContain('contents');
});

test('the metadata column list and the table agree', function () {
    // Guards against a column being added to the table without being added to
    // METADATA_COLUMNS, which would make it silently unreadable everywhere.
    $actual = collect(DB::connection('Content')->getSchemaBuilder()->getColumnListing('content_files'))
        ->reject(fn (string $column): bool => $column === 'contents')
        ->sort()
        ->values()
        ->all();

    expect(collect(ContentFile::METADATA_COLUMNS)->sort()->values()->all())->toBe($actual);
});

test('a published event exposes its image publicly, a draft does not', function () {
    $file = ContentFile::storeUpload(pestUpload());

    $event = Event::create([
        'title' => staffRunToken().' Draft event',
        'starts_at' => now()->addWeek(),
        'image_file_id' => $file->getKey(),
        'image_alt' => 'A test image',
        'is_published' => false,
    ]);

    // Draft: the endpoint must not confirm the file even exists.
    $this->get(route('public.content-files.show', ['file' => $file->getKey()]))
        ->assertNotFound();

    $event->update(['is_published' => true]);

    $response = $this->get(route('public.content-files.show', ['file' => $file->getKey()]));

    $response->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('x-content-type-options', 'nosniff');

    // response() with a string body, not a streamed response, so the
    // bytes are readable straight off the content.
    expect($response->getContent())->toBe(pestPngBytes());

    // Unpublishing takes it offline again.
    $event->update(['is_published' => false]);

    $this->get(route('public.content-files.show', ['file' => $file->getKey()]))
        ->assertNotFound();
});

test('a published newsletter exposes its PDF publicly', function () {
    $file = ContentFile::storeUpload(pestUpload('.pdf', '%PDF-1.4 pest'));

    Newsletter::create([
        'title' => staffRunToken().' Autumn',
        'issue_date' => '2026-09-01',
        'file_id' => $file->getKey(),
        'is_published' => true,
    ]);

    $this->get(route('public.content-files.show', ['file' => $file->getKey()]))
        ->assertOk();
});

test('an orphaned file is never public', function () {
    // Nothing references it, so nothing makes it reachable.
    $file = ContentFile::storeUpload(pestUpload());

    $this->get(route('public.content-files.show', ['file' => $file->getKey()]))
        ->assertNotFound();
});

test('the staff route serves a draft but refuses the public and the inactive', function () {
    $file = ContentFile::storeUpload(pestUpload());

    Event::create([
        'title' => staffRunToken().' Draft',
        'starts_at' => now()->addWeek(),
        'image_file_id' => $file->getKey(),
        'is_published' => false,
    ]);

    $url = route('staff.content-files.show', ['file' => $file->getKey()]);

    // Anonymous: redirected to sign in, not served.
    $this->get($url)->assertRedirect();

    // A deactivated account must not get through either - section 5 rule 4.
    $this->actingAs(staffUser(UserRole::Admin, isActive: false))
        ->get($url)
        ->assertForbidden();

    // A signed-in secretary can preview the draft, which is the point.
    $this->actingAs(staffUser(UserRole::Secretary))
        ->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('a missing file id is a 404, not an error', function () {
    $this->actingAs(staffUser(UserRole::Admin))
        ->get(route('staff.content-files.show', ['file' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

test('the model helpers describe where a file came from', function () {
    $file = ContentFile::storeUpload(pestUpload());

    $event = Event::create([
        'title' => staffRunToken().' With image',
        'starts_at' => now()->addWeek(),
        'image_file_id' => $file->getKey(),
        'is_published' => true,
    ]);

    expect($event->hasImage())->toBeTrue()
        ->and($event->imageUrl())->toContain($file->getKey())
        ->and($event->imageFile->name)->toBe($file->name);

    $without = Event::create([
        'title' => staffRunToken().' No image',
        'starts_at' => now()->addWeek(),
        'is_published' => true,
    ]);

    expect($without->hasImage())->toBeFalse()
        ->and($without->imageUrl())->toBeNull();
});

test('a human-readable size is rendered for the listing', function () {
    $file = ContentFile::storeUpload(pestUpload());

    expect($file->humanSize())->toEndWith('B');

    $big = ContentFile::storeUpload(pestUpload('.pdf', str_repeat('x', 2 * 1048576)));

    expect($big->humanSize())->toBe('2 MB');
});
