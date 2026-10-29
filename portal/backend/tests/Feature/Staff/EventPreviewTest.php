<?php

use Illuminate\Support\Carbon;

/*
The event wizard's Preview step (resources/views/filament/forms/event-preview.blade.php)
shows the event as the website will. Its image carries the event's Image
description as alt text, exactly as the website does (PublicEventResource:
alt ?? "") - never the title, which a screen reader would read instead of what
the picture shows.
*/

function renderEventPreview(array $overrides = []): string
{
    return view('filament.forms.event-preview', [
        'title' => 'Community Walk',
        'summary' => null,
        'description' => null,
        'categoryName' => null,
        'startsAt' => Carbon::parse('2026-11-07 14:00', 'America/New_York'),
        'endsAt' => null,
        'allDay' => false,
        'locationIsVirtual' => false,
        'locationName' => null,
        'locationAddress' => null,
        'locationVirtualNote' => null,
        'registrationUrl' => null,
        'registrationLabel' => null,
        'hasImage' => true,
        'imageUrl' => 'http://localhost/files/example',
        'imageAlt' => 'Volunteers walking with purple banners',
        'isPublished' => true,
        'isCancelled' => false,
        ...$overrides,
    ])->render();
}

test('the preview image uses the Image description as its alt text', function () {
    expect(renderEventPreview())
        ->toContain('alt="Volunteers walking with purple banners"')
        ->not->toContain('alt="Community Walk"');
});

test('with no description the alt text is empty, as on the website', function () {
    expect(renderEventPreview(['imageAlt' => null]))->toContain('alt=""');
});

// The website shows recurrence under the date; the preview must too.
test('the preview shows the recurrence when there is one', function () {
    expect(renderEventPreview(['recurrence' => 'Annually in September']))
        ->toContain('Annually in September');

    expect(renderEventPreview(['recurrence' => null]))
        ->not->toContain('17 1 21 5 17 9');
});

// A field typed to its limit as one unbroken word (a 150-character venue
// name) used to run off the card. Both previews let long words break.
test('the event and newsletter previews wrap long unbroken text', function () {
    expect(renderEventPreview(['locationName' => str_repeat('V', 150)]))
        ->toContain('overflow-wrap: anywhere');

    $newsletter = view('filament.forms.newsletter-preview', [
        'title' => str_repeat('T', 200),
        'summary' => null,
        'issueDate' => null,
        'hasFile' => false,
        'fileSizeBytes' => null,
        'filePages' => null,
        'isPublished' => true,
    ])->render();

    expect($newsletter)->toContain('overflow-wrap: anywhere');
});

// Burchfield B51: the wizard's step header shows each step's name only. The
// Preview step's subtitle ("How your ... will appear on the website.")
// crowded the header and was removed from both wizards.
test('the event and newsletter wizard headers show no Preview subtitle', function () {
    $this->actingAs(staffUser(\App\Enums\UserRole::Secretary));

    foreach ([
        \App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents::class,
        \App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters::class,
    ] as $page) {
        // The pop-up isn't in the page's test HTML, so read its steps from
        // the mounted action's own schema.
        $schema = \Livewire\Livewire::test($page)
            ->mountAction('create')
            ->instance()
            ->getSchema('mountedActionSchema0');

        $preview = collect($schema->getFlatComponents(withHidden: true))
            ->first(fn ($component) => $component instanceof \Filament\Schemas\Components\Wizard\Step
                && $component->getLabel() === 'Preview');

        expect($preview)->not->toBeNull()
            ->and($preview->getDescription())->toBeNull();
    }

    cleanupStaffData();
});
