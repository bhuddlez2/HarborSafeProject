<?php

use App\Enums\UserRole;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Models\County;
use App\Models\ResourceRequestForm;
use App\Support\RecordPdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
The built-in "Other" county (Burchfield B25, 2026-10-28; App\Models\County).
It always exists, is the last choice in every County dropdown, can't be
renamed or deleted, and reads plain "Other" on a record. Everywhere else it
is an ordinary county.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

test('"Other" exists with its fixed id', function () {
    expect(County::find(County::OTHER_ID))
        ->not->toBeNull()
        ->Name->toBe('Other');
});

test('the website lists "Other" last, even after a county is added', function () {
    $added = publicFormsMakeCounty('Pest Later County');

    $ids = collect($this->getJson('/api/public/counties')->assertOk()->json())->pluck('id');

    expect($ids->last())->toBe(County::OTHER_ID)
        ->and($ids)->toContain($added);
});

test('"Other" cannot be renamed or deleted, even outside Form Options', function () {
    $other = County::find(County::OTHER_ID);

    expect(fn () => $other->update(['Name' => 'Elsewhere']))->toThrow(LogicException::class)
        ->and(fn () => $other->delete())->toThrow(LogicException::class)
        ->and(County::find(County::OTHER_ID)->Name)->toBe('Other');

    // Its ChangeDate can still be stamped.
    County::find(County::OTHER_ID)->update(['ChangeDate' => now()]);
});

test('Form Options greys out Edit and Delete on "Other", with the reason on hover', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $other = County::find(County::OTHER_ID);
    $named = County::query()->whereKey(publicFormsMakeCounty('Pest Named County'))->first();

    $page = Livewire::test(ManageCounties::class);

    foreach ([EditAction::class, DeleteAction::class] as $action) {
        $page->assertActionVisible(TestAction::make($action)->table($other))
            ->assertActionDisabled(TestAction::make($action)->table($other))
            ->assertActionExists(
                TestAction::make($action)->table($other),
                fn (Action $button): bool => $button->getTooltip() === 'Built in: "Other" is always offered on the form, so it can\'t be renamed or deleted.',
            )
            ->assertActionEnabled(TestAction::make($action)->table($named));
    }
});

test('a request that chose "Other" reads "Other", never "Other County"', function () {
    $request = new ResourceRequestForm(['FirstName' => 'Pest', 'CountyID' => County::OTHER_ID]);
    $request->setRelation('county', County::find(County::OTHER_ID));
    $request->setRelation('resourceTypes', collect());

    $html = RecordPdf::html($request);

    expect($html)->toContain('Other')->not->toContain('Other County');
});

test('a named county still reads "{Name} County"', function () {
    expect(County::find(1)->displayName())->toBe('Bradley County')
        ->and(County::find(County::OTHER_ID)->displayName())->toBe('Other');
});
