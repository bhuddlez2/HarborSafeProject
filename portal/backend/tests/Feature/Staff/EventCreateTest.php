<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\EventResource;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Models\ContentFile;
use App\Models\Event;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Testing\File as TestingFile;
use Livewire\Livewire;

/*
The event Create pop-up: its second button says what it makes, and the
Preview step shows the image, both one just picked (still a Livewire
temporary upload) and one already saved (EventResource::previewImageUrl()).
*/

afterEach(fn () => cleanupStaffData());

// The Preview step draws a real <img> from $url, not the "Image attached" box.
function previewShows($preview, string $url): bool
{
    $html = (string) $preview->getContent();

    return str_contains($html, '<img src="') && str_contains($html, e($url)) && ! str_contains($html, 'Image attached');
}

test('the second Create button reads Create & Create Another Event', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    expect(EventResource::createAction()->getCreateAnotherAction()->getLabel())
        ->toBe('Create & Create Another Event');
});

// Action steps, like newsletters: Filament drops the footer, so both Create
// buttons show only on the last step (Preview).
test('Create is a step-by-step pop-up with no footer buttons', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $action = Livewire::test(ManageEvents::class)->mountAction('create')->instance()->getMountedAction();

    expect($action->isWizard())->toBeTrue()
        ->and($action->getModalFooterActions())->toBe([]);
});

test('the preview shows an image picked in the pop-up', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $resource = tmpfile();
    fwrite($resource, pestPngOfSize(1200, 675));

    Livewire::test(ManageEvents::class)
        ->mountAction('create')
        ->fillForm([
            'title' => staffRunToken().' Preview image',
            'image_file_id' => new TestingFile(staffRunToken().'-preview.png', $resource),
            'image_alt' => 'A picked image',
        ])
        ->assertSchemaComponentExists('event_preview', checkComponentUsing: fn ($preview): bool => previewShows($preview, 'preview-file'));
});

test('the preview shows a saved image on Edit', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $file = ContentFile::store(pestPngOfSize(1200, 675), staffRunToken().'-saved.png', 'image/png');
    $event = Event::create([
        'title' => staffRunToken().' Saved image',
        'starts_at' => now()->addWeek(),
        'image_file_id' => $file->getKey(),
        'image_alt' => 'A saved image',
        'is_published' => false,
    ]);

    Livewire::test(ManageEvents::class)
        ->mountAction(TestAction::make(EditAction::class)->table($event))
        ->assertSchemaComponentExists('event_preview', checkComponentUsing: fn ($preview): bool => previewShows($preview, route('staff.content-files.show', ['file' => $file->getKey()])));
});
