<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Models\ContentFile;
use App\Models\Event;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
The event image's minimum size (Burchfield B48, 2026-10-28): at least
1200 x 675 pixels, so it stays sharp in the website's 16:9 frames. Checked on
new uploads only; an older event keeps a smaller image when edited.
*/

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-26 12:00', 'America/New_York')));

afterEach(fn () => cleanupStaffData());

function pestEventImage(int $width, int $height): TestingFile
{
    $resource = tmpfile();
    fwrite($resource, pestPngOfSize($width, $height));

    return new TestingFile(staffRunToken()."-{$width}x{$height}.png", $resource);
}

function pestCreateEventWithImage(TestingFile $image): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(ManageEvents::class)
        ->callAction('create', data: [
            'title' => staffRunToken().' Image size '.$image->name,
            'starts_at' => '2027-05-01 10:00',
            'image_file_id' => $image,
            'image_alt' => 'A test image',
        ]);
}

test('an image smaller than the minimum is refused with a clear message', function (int $width, int $height) {
    $this->actingAs(staffUser(UserRole::Secretary));

    pestCreateEventWithImage(pestEventImage($width, $height))
        ->assertHasActionErrors(['image_file_id' => 'The image must be at least 1200 × 675 pixels.']);

    expect(Event::where('title', 'like', staffRunToken().'%')->exists())->toBeFalse();
})->with([
    'too narrow' => [1199, 675],
    'too short' => [1200, 674],
    'a thumbnail' => [300, 200],
]);

test('an image at or above the minimum is accepted', function (int $width, int $height) {
    $this->actingAs(staffUser(UserRole::Secretary));

    pestCreateEventWithImage(pestEventImage($width, $height))
        ->assertHasNoActionErrors();

    expect(Event::where('title', 'like', staffRunToken().'%')->firstOrFail()->image_file_id)->not->toBeNull();
})->with([
    'exactly the minimum' => [1200, 675],
    'a portrait phone photo' => [1440, 1920],
]);

test('the upload box says the minimum', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ManageEvents::class)
        ->mountAction('create')
        ->assertFormFieldExists('image_file_id', fn (DatabaseFileUpload $field): bool => str_contains(
            (string) $field->getChildSchema($field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0]?->getContent(),
            'JPEG, PNG or WebP, at least 1200 × 675 pixels, up to',
        ));
});

test('an older event keeps a smaller image when edited', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $file = ContentFile::store(pestPngOfSize(300, 200), staffRunToken().'-old.png', 'image/png');

    $event = Event::create([
        'title' => staffRunToken().' Older event',
        'starts_at' => now()->addWeek(),
        'image_file_id' => $file->getKey(),
        'image_alt' => 'An older, smaller image',
        'is_published' => false,
    ]);

    Livewire::test(ManageEvents::class)
        ->callAction(TestAction::make(EditAction::class)->table($event), data: [
            'title' => staffRunToken().' Older event, renamed',
        ])
        ->assertHasNoActionErrors();

    expect($event->fresh())
        ->title->toBe(staffRunToken().' Older event, renamed')
        ->image_file_id->toBe($file->getKey());
});
