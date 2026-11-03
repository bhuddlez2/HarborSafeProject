<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Filament\Resources\OfficerAccounts\Pages\ListOfficerAccounts;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Models\Agency;
use App\Models\County;
use App\Models\EventCategory;
use App\Models\Newsletter;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Tables\RecordCards;
use App\Models\Event;
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
    'Events' => [ManageEvents::class, Event::class],
]);

test('the list tables are cards on a phone too, headed by their name', function (Closure $makeUser, string $page, string $model, string $title) {
    $this->actingAs($makeUser());

    $table = Livewire::test($page)->instance()->getTable();

    expect($table->isStackedOnMobile())->toBeTrue()
        ->and($table->getRecordClasses(new $model))->toContain(RecordCards::ROW)
        ->and($table->getColumn($title)->getExtraCellAttributes())->toBe(['class' => RecordCards::ROW.'-title']);
})->with([
    'Services' => [fn () => staffUser(UserRole::Admin), ManageServices::class, Service::class, 'Name'],
    'Resource types' => [fn () => staffUser(UserRole::Admin), ManageResourceTypes::class, Resource::class, 'Name'],
    'Counties' => [fn () => staffUser(UserRole::Admin), ManageCounties::class, County::class, 'Name'],
    'Event categories' => [fn () => staffUser(UserRole::Admin), ManageEventCategories::class, EventCategory::class, 'Name'],
    'Newsletters' => [fn () => staffUser(UserRole::Admin), ManageNewsletters::class, Newsletter::class, 'title'],
    'Agencies' => [fn () => staffUser(UserRole::SuperAdmin), ManageAgencies::class, Agency::class, 'name'],
    'Accounts' => [fn () => staffUser(UserRole::SuperAdmin), ListStaffAccounts::class, User::class, 'name'],
    'Staff Management' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Cards PD')), ListOfficerAccounts::class, User::class, 'name'],
]);

test('both account tables show one Name column, sorted by last name', function (Closure $makeUser, string $page) {
    $this->actingAs($makeUser());

    $table = Livewire::test($page)->instance()->getTable();

    expect($table->getColumn('first_name'))->toBeNull()
        ->and($table->getColumn('last_name'))->toBeNull()
        ->and($table->getColumn('name')->getLabel())->toBe('Name');
})->with([
    'Accounts' => [fn () => staffUser(UserRole::SuperAdmin), ListStaffAccounts::class],
    'Staff Management' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Name column PD')), ListOfficerAccounts::class],
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

// The time is a grey line under the date (B37), in Eastern: 02:30 UTC on the
// 10th is the evening of the 9th. Only forms staff typed in add the Staff tag
// after it.
test('the time sits under the date, with the Staff tag after it only on staff entries', function (string $page, string $model, string $staff, string $online) {
    $this->actingAs(staffUser(UserRole::Admin));

    $column = Livewire::test($page)->instance()->getTable()->getColumn('SubmissionDate');
    $row = fn (string $source) => $column->record((new $model)->forceFill(['Source' => $source, 'SubmissionDate' => '2026-03-10 02:30:00']));

    expect((string) $row($online)->formatState($column->getState()))->toBe('Mar 9, 2026')
        ->and((string) $row($online)->getDescriptionBelow())->toBe('10:30 pm')
        ->and((string) $row($staff)->getDescriptionBelow())->toBe('10:30 pm '.RecordCards::staffTag());
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
