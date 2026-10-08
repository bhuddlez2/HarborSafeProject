<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Models\ContentFile;
use App\Models\Event;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Upload size limits.

Written after a 3.5 MB event photo was rejected with an error that pointed at
nothing useful. The cause was PHP, not this application: a stock php.ini sets
upload_max_filesize to 2M, so PHP discarded the file before Laravel ran, and
none of Filament's validation messages ever got a chance to appear. The panel
meanwhile advertised "up to 4 MB", which it could not honour.

So there are two different things to keep honest, and both are below:

  - The arithmetic that clamps the advertised limit to what PHP will actually
    take, so the helper text can never over-promise again.
  - That a 3.5 MB file is fine once it reaches the application.

A CAVEAT ON THE SECOND. Illuminate\Http\Testing\File does not travel through
PHP's upload handling at all, so these tests would have passed even with
upload_max_filesize at 2M. They prove Filament, Livewire and MariaDB accept the
file; they cannot prove the server is configured to deliver it. Only a real
browser upload shows that, which is why the limits are also asserted against
ini_get() directly in the first test here.
*/

afterEach(fn () => cleanupStaffData());

test('the advertised limit never exceeds what PHP will accept', function () {
    $ceiling = DatabaseFileUpload::phpUploadCeilingKb();

    // Null only when both directives are unset or unlimited, which is not a
    // configuration this project expects.
    expect($ceiling)->not->toBeNull();

    foreach ([DatabaseFileUpload::MAX_IMAGE_KB, DatabaseFileUpload::MAX_DOCUMENT_KB] as $intended) {
        expect(DatabaseFileUpload::effectiveLimitKb($intended))
            ->toBeLessThanOrEqual($ceiling)
            ->toBeLessThanOrEqual($intended);
    }
});

test('this environment can actually carry the intended limits', function () {
    /*
    Not a test of application logic - a check on the machine running the suite,
    and the one that would have caught the original report. If it fails, raise
    upload_max_filesize and post_max_size in php.ini; see PORTAL_SETUP.md.

    post_max_size bounds the whole request, so it has to exceed
    upload_max_filesize rather than merely match it.
    */
    $ceiling = DatabaseFileUpload::phpUploadCeilingKb();

    expect($ceiling)->toBeGreaterThanOrEqual(
        DatabaseFileUpload::MAX_DOCUMENT_KB,
        'php.ini cannot carry an 8 MB newsletter: raise upload_max_filesize and post_max_size.',
    );
});

test('a 3.5 MB event photo is accepted', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // The exact size from the report, in real bytes.
    $bytes = 3_500_000;
    $resource = tmpfile();
    fwrite($resource, str_repeat('x', $bytes));
    $upload = new TestingFile(staffRunToken().'-photo.png', $resource);

    $title = staffRunToken().' Big photo event';

    Livewire::test(ManageEvents::class)
        ->callAction('create', data: [
            'title' => $title,
            'starts_at' => '2027-07-01 10:00',
            'image_file_id' => $upload,
            'image_alt' => 'A large test photo',
            'is_published' => true,
        ])
        ->assertHasNoActionErrors();

    $event = Event::where('title', $title)->firstOrFail();
    $file = ContentFile::find($event->image_file_id);

    expect($file)->not->toBeNull()
        ->and($file->size_bytes)->toBe($bytes)
        // The blob survived the round trip at this size, which is the part
        // max_allowed_packet would break.
        ->and(strlen($file->contents()))->toBe($bytes);
});

test('a file over the limit is rejected with a validation error', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // One kilobyte past the effective image limit.
    $overBy = (DatabaseFileUpload::effectiveLimitKb(DatabaseFileUpload::MAX_IMAGE_KB) + 1) * 1024;
    $resource = tmpfile();
    fwrite($resource, str_repeat('x', $overBy));

    Livewire::test(ManageEvents::class)
        ->callAction('create', data: [
            'title' => staffRunToken().' Too big',
            'starts_at' => '2027-07-01 10:00',
            'image_file_id' => new TestingFile(staffRunToken().'-huge.png', $resource),
            'image_alt' => 'Too large',
        ])
        // A clear message about the file, rather than a silent failure.
        ->assertHasActionErrors(['image_file_id']);
});

test('the limit stays inside the database packet ceiling', function () {
    /*
    max_allowed_packet caps a single row in both directions, so a blob that
    squeezes past PHP can still be unreadable afterwards. The document limit
    plus query overhead has to fit.
    */
    $packetBytes = (int) collect(DB::connection('Content')->select("SHOW VARIABLES LIKE 'max_allowed_packet'"))
        ->first()
        ->Value;

    expect(DatabaseFileUpload::MAX_DOCUMENT_KB * 1024)
        ->toBeLessThan(
            $packetBytes,
            'The document upload limit exceeds max_allowed_packet; stored files would fail to read back.',
        );
});
