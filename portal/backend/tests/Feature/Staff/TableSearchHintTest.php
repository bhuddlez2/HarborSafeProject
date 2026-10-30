<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Filament\Resources\OfficerAccounts\Pages\ListOfficerAccounts;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use Livewire\Livewire;

/*
Burchfield B38: every table's search box says "Search" until it is clicked,
then what it searches ("Search by Title"), from the table's
->searchPlaceholder(). The swap is in our copy of Filament's search box
(resources/views/vendor/filament-tables/components/search-field.blade.php).
*/

afterEach(fn () => cleanupStaffData());

test('each search box says what it searches', function (string $page, string $hint, UserRole $role = UserRole::SuperAdmin) {
    $this->actingAs($role === UserRole::SuperAdmin
        ? staffUser($role)
        : pestAgencyMember($role, pestAgency('Search Hint PD')));

    expect(Livewire::test($page)->instance()->getTable()->getSearchPlaceholder())->toBe($hint);
})->with([
    'Events' => [ManageEvents::class, 'Search by Title'],
    'Newsletters' => [ManageNewsletters::class, 'Search by Title'],
    'Categories' => [ManageEventCategories::class, 'Search by Name'],
    'Services' => [ManageServices::class, 'Search by Name'],
    'Resource types' => [ManageResourceTypes::class, 'Search by Name'],
    'Counties' => [ManageCounties::class, 'Search by Name'],
    'Service feedback' => [ListServiceFeedback::class, 'Search by Comment'],
    'Resource requests' => [ListResourceRequests::class, 'Search by Name or Email'],
    'Agencies' => [ManageAgencies::class, 'Search by Name'],
    'Logs' => [ListLogs::class, 'Search by Name'],
    'Accounts' => [ListStaffAccounts::class, 'Search by Name or Email'],
    'Officers' => [ListOfficerAccounts::class, 'Search by Name, Email or Badge Number', UserRole::PoliceAdmin],
]);

test('the box says Search until clicked, then the hint', function () {
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(ListStaffAccounts::class)
        ->assertSeeHtml('placeholder="Search"')
        ->assertDontSeeHtml('placeholder="Search by Name or Email"')
        ->assertSeeHtml('x-on:focus="$el.placeholder = \'Search by Name or Email\'"')
        ->assertSeeHtml('x-on:blur="$el.placeholder = \'Search\'"')
        ->assertSeeHtml('wire:ignore.self');
});
