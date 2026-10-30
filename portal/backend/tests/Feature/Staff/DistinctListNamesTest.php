<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Models\Agency;
use App\Models\County;
use App\Models\EventCategory;
use App\Models\Resource as ResourceType;
use App\Models\Service;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
No duplicates or sound-alikes on the lists staff add to (Burchfield B55/B60,
2026-10-28; App\Rules\DistinctListName): event categories, services, resource
types, counties and agencies. Every name here starts with the run's
"peststaff..." token, so it can't clash with a real row and is swept up.
*/

afterEach(function () {
    foreach (['services', 'resources', 'counties'] as $table) {
        DB::connection('Feedback')->table($table)->where('Name', 'like', STAFF_TEST_PREFIX.'%')->delete();
    }
    cleanupStaffData();
});

dataset('lists', [
    'event categories' => [ManageEventCategories::class, EventCategory::class, 'Name', UserRole::Admin],
    'services' => [ManageServices::class, Service::class, 'Name', UserRole::Admin],
    'resource types' => [ManageResourceTypes::class, ResourceType::class, 'Name', UserRole::Admin],
    'counties' => [ManageCounties::class, County::class, 'Name', UserRole::Admin],
    'agencies' => [ManageAgencies::class, Agency::class, 'name', UserRole::SuperAdmin],
]);

test('a name that sounds like one on the list is refused, naming it', function (string $page, string $model, string $column, UserRole $role) {
    $this->actingAs(staffUser($role));
    $existing = staffRunToken().' Counseling';
    $model::query()->create([$column => $existing]);

    Livewire::test($page)
        ->callAction('create', [$column => staffRunToken().' Counselling'])
        ->assertHasFormErrors([$column]);

    Livewire::test($page)
        ->callAction('create', [$column => strtoupper(staffRunToken()).'  COUNSELING'])
        ->assertHasFormErrors([$column]);

    expect($model::query()->where($column, 'like', staffRunToken().'%')->count())->toBe(1);
})->with('lists');

test('a distinct name is saved with its spaces tidied', function (string $page, string $model, string $column, UserRole $role) {
    $this->actingAs(staffUser($role));
    $model::query()->create([$column => staffRunToken().' Counseling']);

    Livewire::test($page)
        ->callAction('create', [$column => '  '.staffRunToken().'   Legal   Help  '])
        ->assertHasNoFormErrors();

    expect($model::query()->where($column, staffRunToken().' Legal Help')->exists())->toBeTrue();
})->with('lists');

test('renaming checks the other rows, never the row itself', function (string $page, string $model, string $column, UserRole $role) {
    $this->actingAs(staffUser($role));
    $row = $model::query()->create([$column => staffRunToken().' Counseling']);
    $model::query()->create([$column => staffRunToken().' Transportation']);

    // Fixing its own capitals is fine.
    Livewire::test($page)
        ->callAction(TestAction::make('edit')->table($row), [$column => staffRunToken().' COUNSELING'])
        ->assertHasNoFormErrors();

    // Renaming it to sound like the other is not.
    Livewire::test($page)
        ->callAction(TestAction::make('edit')->table($row), [$column => staffRunToken().' Transportaton'])
        ->assertHasFormErrors([$column]);
})->with('lists');

test('a row already next to a look-alike can be saved unchanged', function (string $page, string $model, string $column, UserRole $role) {
    $this->actingAs(staffUser($role));
    // Two rows that predate the rule.
    $row = $model::query()->create([$column => staffRunToken().' Training']);
    $model::query()->create([$column => staffRunToken().' Trainning']);

    Livewire::test($page)
        ->callAction(TestAction::make('edit')->table($row), [$column => staffRunToken().' Training'])
        ->assertHasNoFormErrors();
})->with('lists');

test('nobody can add a look-alike of the built-in "Other" county', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ManageCounties::class)
        ->callAction('create', ['Name' => 'Othar'])
        ->assertHasFormErrors(['Name']);

    Livewire::test(ManageCounties::class)
        ->callAction('create', ['Name' => 'Others'])
        ->assertHasFormErrors(['Name']);
});
