<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Models\ContentFile;
use App\Models\Event;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Where an event's image sits on the website's card (2026-10-30): staff drag and
zoom it on the Image step, saved as {x, y, zoom} in events.image_position and
clamped on save by Event::normalizeImagePosition(). The View Details pop-up
shows the whole image, so nothing else needs positioning.
*/

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-26 12:00', 'America/New_York')));

afterEach(fn () => cleanupStaffData());

function pestPositionedEvent(?array $position = null): Event
{
    return Event::create([
        'title' => staffRunToken().' Positioned flyer',
        'starts_at' => now()->addWeek(),
        'image_file_id' => ContentFile::store(pestPngOfSize(1080, 1350), staffRunToken().'-flyer.png', 'image/png')->getKey(),
        'image_alt' => 'A flyer',
        'image_position' => $position,
        'is_published' => false,
    ]);
}

test('a position is clamped to its ranges, and anything unreadable is centred', function (mixed $given, array $saved) {
    expect(Event::normalizeImagePosition($given))->toEqual($saved);
})->with([
    'in range' => [['x' => 20, 'y' => 0, 'zoom' => 1.5], ['x' => 20, 'y' => 0, 'zoom' => 1.5]],
    'out of range' => [['x' => -5, 'y' => 140, 'zoom' => 9], ['x' => 0, 'y' => 100, 'zoom' => Event::MAX_IMAGE_ZOOM]],
    'zoomed out past 1' => [['x' => 50, 'y' => 50, 'zoom' => 0.2], ['x' => 50, 'y' => 50, 'zoom' => 1]],
    'not numbers' => [['x' => 'left', 'y' => null], Event::DEFAULT_IMAGE_POSITION],
    'never set' => [null, Event::DEFAULT_IMAGE_POSITION],
]);

test('the Edit pop-up shows the position box for an event with an image', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // Pop-up contents aren't in a test's HTML, so the field is rendered on its own.
    Livewire::test(ManageEvents::class)
        ->mountAction(TestAction::make(EditAction::class)->table(pestPositionedEvent(['x' => 20, 'y' => 0, 'zoom' => 1.5])))
        ->assertFormFieldExists('image_position', function (ViewField $field): bool {
            view()->share('errors', new \Illuminate\Support\ViewErrorBag);
            $html = $field->toHtml();

            return $field->getLabel() === 'Position on the event card'
                && str_contains($html, '/files/')
                && str_contains($html, 'Zoom');
        });
});

test('the chosen position is saved, clamped', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $event = pestPositionedEvent();

    Livewire::test(ManageEvents::class)
        ->callAction(TestAction::make(EditAction::class)->table($event), data: [
            'image_position' => ['x' => 30, 'y' => 150, 'zoom' => 1.8],
        ])
        ->assertHasNoActionErrors();

    expect($event->fresh()->image_position)->toEqual(['x' => 30, 'y' => 100, 'zoom' => 1.8]);
});

test('the Preview step draws the card image at the chosen position', function () {
    $html = view('filament.forms.event-preview', [
        'title' => 'Flyer', 'summary' => null, 'description' => null, 'categoryName' => null,
        'startsAt' => null, 'endsAt' => null, 'allDay' => false, 'locationIsVirtual' => false,
        'locationName' => null, 'locationAddress' => null, 'locationVirtualNote' => null,
        'registrationUrl' => null, 'registrationLabel' => null, 'hasImage' => true,
        'imageUrl' => 'http://localhost/files/example', 'imageAlt' => 'A flyer',
        'imagePosition' => ['x' => 20, 'y' => 0, 'zoom' => 1.5],
        'recurrence' => null, 'isPublished' => true, 'isCancelled' => false,
    ])->render();

    expect($html)->toContain('object-position: 20% 0%')
        ->toContain('transform: scale(1.5)');
});
