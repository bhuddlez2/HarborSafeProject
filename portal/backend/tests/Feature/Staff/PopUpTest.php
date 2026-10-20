<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use Filament\Support\View\Components\ModalComponent;
use Livewire\Livewire;

/*
Pop-ups close only from their X, Cancel or the Esc key, never from a click
outside them (team plan task 5): a stray click used to throw away a half-typed
event. Set once for the whole panel in AppServiceProvider, so any new pop-up
follows it.

The other half of task 5 - the filter menu no longer cut off inside short
tables - is a CSS rule in theme.css (.fi-ta-ctn), which needs a browser to see.
*/

afterEach(fn () => cleanupStaffData());

test('pop-ups are not closed by clicking outside them, but Esc still closes them', function () {
    expect(ModalComponent::$isClosedByClickingAway)->toBeFalse()
        ->and(ModalComponent::$isClosedByEscaping)->toBeTrue();
});

test('the New event pop-up keeps its form when someone clicks outside it', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $action = Livewire::test(ManageEvents::class)->instance()->getAction('create');

    expect($action->isModalClosedByClickingAway())->toBeFalse()
        ->and($action->isModalClosedByEscaping())->toBeTrue();
});
