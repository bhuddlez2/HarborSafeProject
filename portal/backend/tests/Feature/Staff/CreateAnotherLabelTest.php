<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use Livewire\Livewire;

afterEach(fn () => cleanupStaffData());

// Button labels are title case: every Create pop-up's second button reads
// "Create & Create Another" (AppServiceProvider), not Filament's
// "Create & create another". Events has its own, in EventCreateTest.
test('every Create pop-up offers Create & Create Another', function (UserRole $role, string $page) {
    $this->actingAs(staffUser($role));

    $action = Livewire::test($page)->mountAction('create')->instance()->getMountedAction();

    expect($action->getCreateAnotherAction()->getLabel())->toBe('Create & Create Another');
})->with([
    'Event categories' => [UserRole::Admin, ManageEventCategories::class],
    'Services' => [UserRole::Admin, ManageServices::class],
    'Resource types' => [UserRole::Admin, ManageResourceTypes::class],
    'Counties' => [UserRole::Admin, ManageCounties::class],
    'Agencies' => [UserRole::SuperAdmin, ManageAgencies::class],
]);
