<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Analytics\Pages\FeedbackAnalytics;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Models\Event;
use App\Models\Newsletter;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Realistic ranges for typed dates (dates of birth are DateOfBirthTest):

  - event start/end: a year back to two years ahead;
  - newsletter publish date: the 1st of the month six months back to today,
    or with Schedule post to the end of the month six months ahead;
  - a staff-entered feedback form: up to two years back, never ahead;
  - the tables' from/until filters and the Analytics custom range: no future
    day, and the end not before the start.

Events and newsletters are checked only when a date is new or changed, so an
older record can still be edited.

"Now" is fixed at noon Eastern on 26 October 2026.
*/

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-26 12:00', 'America/New_York')));

afterEach(fn () => cleanupStaffData());

test('a new event starts and ends within a year back and two years ahead', function (string $field, string $value, bool $allowed) {
    $this->actingAs(staffUser(UserRole::Secretary));

    $data = ['title' => staffRunToken().' Ranged event', 'starts_at' => '2027-01-10 18:00', $field => $value];
    if ($field === 'ends_at') {
        $data['starts_at'] = '2025-10-26 09:00';
    }

    $page = Livewire::test(ManageEvents::class)->callAction('create', data: $data);

    $allowed ? $page->assertHasNoActionErrors() : $page->assertHasActionErrors([$field]);
})->with([
    'start, the earliest day' => ['starts_at', '2025-10-26 09:00', true],
    'start, the day before' => ['starts_at', '2025-10-25 18:00', false],
    'start, the latest day' => ['starts_at', '2028-10-26 18:00', true],
    'start, the day after' => ['starts_at', '2028-10-27 09:00', false],
    'end, the latest day' => ['ends_at', '2028-10-26 18:00', true],
    'end, the day after' => ['ends_at', '2028-10-27 09:00', false],
]);

test('an older event can still be edited, but not moved to another old date', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $event = Event::create([
        'title' => staffRunToken().' Old event',
        'starts_at' => Carbon::parse('2023-05-01 18:00', 'America/New_York')->utc(),
        'is_published' => false,
    ]);

    Livewire::test(ManageEvents::class)
        ->callAction(TestAction::make(EditAction::class)->table($event), data: ['title' => staffRunToken().' Old event, renamed'])
        ->assertHasNoActionErrors();

    expect($event->fresh()->starts_at->eq(Carbon::parse('2023-05-01 18:00', 'America/New_York')))->toBeTrue();

    Livewire::test(ManageEvents::class)
        ->callAction(TestAction::make(EditAction::class)->table($event), data: ['starts_at' => '2023-06-01 18:00'])
        ->assertHasActionErrors(['starts_at']);
});

test('a newsletter publish date runs from six months back to today, or six months ahead when scheduled', function (string $date, bool $scheduled, bool $allowed) {
    $this->actingAs(staffUser(UserRole::Secretary));

    $page = Livewire::test(ManageNewsletters::class)
        ->callAction('create', data: ['title' => staffRunToken().' Issue', 'issue_date' => $date, 'schedule_post' => $scheduled]);

    // Only the date is judged here; the test sends no file.
    $allowed ? $page->assertHasNoActionErrors(['issue_date']) : $page->assertHasActionErrors(['issue_date']);
})->with([
    '1 April, the 1st of the month six months back' => ['2026-04-01', false, true],
    'the day before' => ['2026-03-31', false, false],
    'today' => ['2026-10-26', false, true],
    'tomorrow, not scheduled' => ['2026-10-27', false, false],
    'tomorrow, scheduled' => ['2026-10-27', true, true],
    '30 April 2027, scheduled' => ['2027-04-30', true, true],
    '1 May 2027, scheduled' => ['2027-05-01', true, false],
    'six months back, scheduled' => ['2026-04-01', true, true],
]);

test('the publish date explains what went wrong', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ManageNewsletters::class)
        ->callAction('create', data: ['title' => staffRunToken().' Issue', 'issue_date' => '2026-03-31'])
        ->assertHasActionErrors(['issue_date' => "Can't be before April 1, 2026."]);

    Livewire::test(ManageNewsletters::class)
        ->callAction('create', data: ['title' => staffRunToken().' Issue', 'issue_date' => '2026-11-02'])
        ->assertHasActionErrors(['issue_date' => "Can't be in the future. Tick Schedule post to choose a later date."]);

    Livewire::test(ManageNewsletters::class)
        ->callAction('create', data: ['title' => staffRunToken().' Issue', 'issue_date' => '2027-05-01', 'schedule_post' => true])
        ->assertHasActionErrors(['issue_date' => "Can't be after April 30, 2027."]);
});

test('a scheduled newsletter opens with Schedule post ticked, and unticking it refuses its future date', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $newsletter = Newsletter::create([
        'title' => staffRunToken().' Winter issue',
        'issue_date' => '2026-12-01',
        // Edit requires a PDF (Burchfield B53).
        'file_id' => pestStoredPdf()->getKey(),
        'is_published' => true,
    ]);

    Livewire::test(ManageNewsletters::class)
        ->mountAction(TestAction::make(EditAction::class)->table($newsletter))
        ->assertActionDataSet(['schedule_post' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    Livewire::test(ManageNewsletters::class)
        ->callAction(TestAction::make(EditAction::class)->table($newsletter), data: ['schedule_post' => false])
        ->assertHasActionErrors(['issue_date']);

    expect($newsletter->fresh()->issue_date->toDateString())->toBe('2026-12-01');
});

test('an older newsletter keeps its date when edited', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $newsletter = Newsletter::create([
        'title' => staffRunToken().' Archive issue',
        'issue_date' => '1998-03-01',
        'is_published' => false,
    ]);

    Livewire::test(ManageNewsletters::class)
        ->callAction(TestAction::make(EditAction::class)->table($newsletter), data: ['title' => staffRunToken().' Archive issue, renamed'])
        ->assertHasNoActionErrors(['issue_date']);

    Livewire::test(ManageNewsletters::class)
        ->callAction(TestAction::make(EditAction::class)->table($newsletter), data: ['issue_date' => '1998-04-01'])
        ->assertHasActionErrors(['issue_date']);
});

test('a staff-entered feedback form is dated up to two years back, never ahead', function (string $when, bool $allowed) {
    $this->actingAs(staffUser(UserRole::Secretary));

    $page = Livewire::test(ListServiceFeedback::class)
        ->callAction('create', data: ['ServiceID' => publicFormsMakeService(), 'Rating' => 3, 'SubmissionDate' => $when]);

    $allowed ? $page->assertHasNoActionErrors() : $page->assertHasActionErrors(['SubmissionDate']);
})->with([
    'two years ago, first thing' => ['2024-10-26 00:00', true],
    'the evening before that' => ['2024-10-25 20:00', false],
    'earlier today' => ['2026-10-26 09:00', true],
    'tomorrow' => ['2026-10-27 09:00', false],
])->after(fn () => cleanupPublicFormData());

test('the table date filters offer no future day, and "until" nothing before "from"', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $list = Livewire::test(ListFollowUps::class)
        ->set('tableDeferredFilters.date_submitted.from', '2026-03-10');

    $picker = fn (string $field): DatePicker => $list->instance()->getTableFiltersForm()->getComponent(
        fn ($component): bool => $component instanceof DatePicker && str_ends_with($component->getStatePath(), "date_submitted.{$field}"),
        withHidden: true,
    );

    expect(substr($picker('from')->getMaxDate(), 0, 10))->toBe('2026-10-26')
        ->and(substr($picker('until')->getMaxDate(), 0, 10))->toBe('2026-10-26')
        ->and(substr($picker('until')->getMinDate(), 0, 10))->toBe('2026-03-10');

    // "from" can't be after a chosen "until".
    $list->set('tableDeferredFilters.date_submitted.until', '2026-06-30');

    expect(substr($picker('from')->getMaxDate(), 0, 10))->toBe('2026-06-30');
});

test('the Analytics custom range can\'t reach into the future', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(FeedbackAnalytics::class)
        ->call('setRange', 'custom')
        ->set('customFrom', '2026-10-01')
        ->set('customTo', '2026-10-27')
        ->call('applyCustomRange')
        ->assertHasErrors(['customTo' => 'before_or_equal'])
        ->set('customTo', '2026-10-26')
        ->call('applyCustomRange')
        ->assertHasNoErrors()
        ->assertSeeHtml('max="2026-10-26"');
});
