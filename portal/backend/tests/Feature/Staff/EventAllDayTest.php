<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Models\Event;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Burchfield B46: with All-day event on, Starts and Ends ask for a date only. The
day is saved at midnight Eastern, so it is the same day in Eastern on the panel
and the website, either side of a DST change, and never the evening before.
*/

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-26 12:00', 'America/New_York')));

afterEach(fn () => cleanupStaffData());

test('an all-day event is saved at midnight Eastern on its day', function (string $day, string $utc) {
    $this->actingAs(staffUser(UserRole::Secretary));
    $title = staffRunToken().' All-day fair';

    Livewire::test(ManageEvents::class)
        ->callAction('create', data: [
            'title' => $title,
            'all_day' => true,
            'starts_at' => $day,
        ])
        ->assertHasNoActionErrors();

    $event = Event::where('title', $title)->firstOrFail();

    expect($event->all_day)->toBeTrue()
        ->and($event->starts_at->toDateTimeString())->toBe($utc)
        ->and($event->starts_at->timezone(Event::DISPLAY_TIMEZONE)->toDateString())->toBe($day);
})->with([
    'summer time' => ['2027-03-20', '2027-03-20 04:00:00'],
    'winter time' => ['2027-01-15', '2027-01-15 05:00:00'],
]);

test('an all-day event opens on its Eastern day and saves unchanged', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // 9pm Eastern: already the next day in UTC.
    $startsAt = Carbon::parse('2027-03-20 21:00', 'America/New_York')->utc();
    $event = Event::create([
        'title' => staffRunToken().' Evening fair',
        'starts_at' => $startsAt,
        'all_day' => true,
    ]);

    // The form's saved value: the Eastern day at midnight, in UTC.
    Livewire::test(ManageEvents::class)
        ->mountAction(TestAction::make(EditAction::class)->table($event))
        ->assertActionDataSet(['starts_at' => '2027-03-20 04:00:00'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($event->fresh()->starts_at->timezone(Event::DISPLAY_TIMEZONE)->toDateString())->toBe('2027-03-20');
});

test('turning All-day event on and off keeps the start day and clears the end', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ManageEvents::class)
        ->mountAction('create')
        ->fillForm(['starts_at' => '2027-03-20 17:30', 'ends_at' => '2027-03-21 10:00'])
        ->fillForm(['all_day' => true])
        ->assertSet('mountedActions.0.data.starts_at', '2027-03-20')
        ->assertSet('mountedActions.0.data.ends_at', null)
        ->assertFormFieldDisabled('ends_at')
        ->fillForm(['multiple_days' => true])
        ->assertFormFieldEnabled('ends_at')
        ->fillForm(['all_day' => false])
        ->assertSet('mountedActions.0.data.starts_at', '2027-03-20 00:00')
        ->assertFormFieldEnabled('ends_at');
});

// Ends is greyed out for a one-day all-day event and saved empty, even when a
// tampered request sends one.
test('a one-day all-day event is saved with no end', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $title = staffRunToken().' One-day fair';
    Livewire::test(ManageEvents::class)
        ->callAction('create', data: ['title' => $title, 'all_day' => true, 'starts_at' => '2027-03-20', 'ends_at' => '2027-03-22'])
        ->assertHasNoActionErrors();

    expect(Event::where('title', $title)->firstOrFail()->ends_at)->toBeNull();
});

// With Multiple days on, Ends is a date too, saved at midnight Eastern.
test('an all-day event can run over several days', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $title = staffRunToken().' Two-day fair';
    Livewire::test(ManageEvents::class)
        ->callAction('create', data: ['title' => $title, 'all_day' => true, 'multiple_days' => true, 'starts_at' => '2027-03-20', 'ends_at' => '2027-03-21'])
        ->assertHasNoActionErrors();

    $event = Event::where('title', $title)->firstOrFail();
    expect($event->ends_at->toDateTimeString())->toBe('2027-03-21 04:00:00');

    // Edit opens with Multiple days on and keeps the end.
    Livewire::test(ManageEvents::class)
        ->mountAction(TestAction::make(EditAction::class)->table($event))
        ->assertActionDataSet(['multiple_days' => true, 'ends_at' => '2027-03-21 04:00:00'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($event->fresh()->ends_at->toDateTimeString())->toBe('2027-03-21 04:00:00');

    // The end must be a later day.
    Livewire::test(ManageEvents::class)
        ->callAction('create', data: ['title' => staffRunToken().' Same-day fair', 'all_day' => true, 'multiple_days' => true, 'starts_at' => '2027-03-20', 'ends_at' => '2027-03-20'])
        ->assertHasActionErrors(['ends_at']);
});

test('the preview shows an all-day event on its own day', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    // The pop-up isn't in the page's test HTML, so render the Preview from the form.
    $schema = Livewire::test(ManageEvents::class)
        ->mountAction('create')
        ->fillForm(['title' => staffRunToken().' Fair', 'all_day' => true, 'starts_at' => '2027-03-20'])
        ->instance()
        ->getSchema('mountedActionSchema0');

    $preview = (string) collect($schema->getFlatComponents(withHidden: true))
        ->first(fn ($component) => $component instanceof Placeholder && $component->getName() === 'event_preview')
        ->getContent();

    expect($preview)->toContain('March 20, 2027')
        ->not->toContain('March 19, 2027');
});
