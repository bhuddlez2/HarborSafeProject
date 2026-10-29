<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Tables\RecordCards;
use App\Models\FollowUp;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use Filament\Tables\Columns\Column;
use Livewire\Livewire;

/*
Service feedback, Resource requests and Follow-ups as cards on a phone, with
stacked buttons on narrower screens (App\Filament\Tables\RecordCards,
Finishing Touches item 9), and the columns the two Submissions tables kept:
Source became a "Staff" tag under the date, and the requester's email a grey
line under the name. The assessment tables' share of this is in
AssessmentTableColumnsTest.
*/

afterEach(fn () => cleanupStaffData());

function cardTableLabels(string $page): array
{
    return collect(Livewire::test($page)->instance()->getTable()->getColumns())
        ->map(fn (Column $column): string => (string) $column->getLabel())
        ->values()
        ->all();
}

test('each table is a card on a phone', function (string $page, string $model) {
    $this->actingAs(staffUser(UserRole::Admin));

    $table = Livewire::test($page)->instance()->getTable();

    expect($table->isStackedOnMobile())->toBeTrue()
        // theme.css lays the card out from this class on each row.
        ->and($table->getRecordClasses(new $model))->toContain(RecordCards::ROW);
})->with([
    'Service feedback' => [ListServiceFeedback::class, ServiceFeedback::class],
    'Resource requests' => [ListResourceRequests::class, ResourceRequestForm::class],
    'Follow-ups' => [ListFollowUps::class, FollowUp::class],
]);

test('Service feedback has four columns, with no Source column', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(cardTableLabels(ListServiceFeedback::class))->toBe(['Submitted', 'Service', 'Rating', 'Comment']);
});

test('Resource requests has five columns, with Follow-Up and no Source or Email column', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(cardTableLabels(ListResourceRequests::class))->toBe(['Submitted', 'Name', 'County', 'Interested in', 'Follow-Up']);
});

test('Follow-ups keeps its columns', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(cardTableLabels(ListFollowUps::class))->toBe(['Name', 'Type', 'Submitted', 'Last updated']);
});

// Only forms staff typed in say so; website forms show nothing under the date.
test('the Staff tag shows under the date only on staff entries', function (string $page, string $model, string $staff, string $online) {
    $this->actingAs(staffUser(UserRole::Admin));

    $column = Livewire::test($page)->instance()->getTable()->getColumn('SubmissionDate');
    $descriptionFor = fn (string $source) => $column->record((new $model)->forceFill(['Source' => $source]))->getDescriptionBelow();

    expect($descriptionFor($staff))->toEqual(RecordCards::staffTag())
        ->and($descriptionFor($online))->toBeNull();
})->with([
    'Service feedback' => [ListServiceFeedback::class, ServiceFeedback::class, ServiceFeedback::SOURCE_STAFF, ServiceFeedback::SOURCE_ONLINE],
    'Resource requests' => [ListResourceRequests::class, ResourceRequestForm::class, ResourceRequestForm::SOURCE_STAFF, ResourceRequestForm::SOURCE_ONLINE],
]);

test('the requester\'s email sits under the name', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $column = Livewire::test(ListResourceRequests::class)->instance()->getTable()->getColumn('FirstName');
    $request = (new ResourceRequestForm)->forceFill(['FirstName' => 'Dana', 'EmailAddress' => 'dana.smith@example.test']);

    expect((string) $column->record($request)->getDescriptionBelow())->toBe('dana.<wbr>smith<wbr>@example.<wbr>test')
        ->and($column->record((new ResourceRequestForm)->forceFill(['FirstName' => 'Dana']))->getDescriptionBelow())->toBeNull();
});

// What the public typed is escaped before the line-break hints go in.
test('the email is escaped before it is shown', function () {
    expect((string) RecordCards::email('<b>x</b>@example.test'))
        ->toBe('&lt;b&gt;x&lt;/b&gt;<wbr>@example.<wbr>test')
        ->and(RecordCards::email(null))->toBeNull()
        ->and(RecordCards::email(''))->toBeNull();
});
