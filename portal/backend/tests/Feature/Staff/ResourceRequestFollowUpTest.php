<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Models\ResourceRequestForm;
use App\Services\FollowUps;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
Burchfield B58 (AS 2026-10-28): Submissions → Resource Requests shows each
request's follow-up status, and a Follow-Up button opening its follow-up
page, where Update Follow-Up is. Nothing is updated from the list itself, so
FollowUps::update() stays the one way to change a follow-up.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

test('each request shows its follow-up status and opens its follow-up page', function (UserRole $role) {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $request = ResourceRequestForm::findOrFail($followUp->ResourceRequestFormID);
    $user = staffUser($role);

    $this->actingAs($user);

    Livewire::test(ListResourceRequests::class)
        ->assertTableColumnStateSet('follow_up_status', 'Needs Follow-Up', $request)
        ->assertActionVisible(TestAction::make('follow_up')->table($request))
        ->assertActionHasUrl(TestAction::make('follow_up')->table($request), $followUp->url());

    app(FollowUps::class)->update($followUp, FollowUpStatus::Reached, 'Pest: called back', $user);

    Livewire::test(ListResourceRequests::class)
        ->assertTableColumnStateSet('follow_up_status', 'Reached', $request);
})->with([
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
    'super admin' => UserRole::SuperAdmin,
]);

test('a request with no follow-up shows none and no button', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $request = ResourceRequestForm::findOrFail($followUp->ResourceRequestFormID);
    cleanupFollowUps('ResourceRequestFormID', [$request->getKey()]);

    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ListResourceRequests::class)
        ->assertTableColumnStateNotSet('follow_up_status', 'Needs Follow-Up', $request)
        ->assertActionHidden(TestAction::make('follow_up')->table($request));
});

// Burchfield B56 (AS 2026-10-30): requests are kept; only admins and super
// admins may delete one (spam), row or bulk; secretaries can't.
test('only admins and super admins can delete a request', function (UserRole $role, bool $canDelete) {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $request = ResourceRequestForm::findOrFail($followUp->ResourceRequestFormID);

    $this->actingAs(staffUser($role));

    $page = Livewire::test(ListResourceRequests::class);

    if ($canDelete) {
        $page->assertActionVisible(TestAction::make(DeleteAction::class)->table($request))
            ->assertActionVisible(TestAction::make(DeleteBulkAction::class)->table()->bulk());
    } else {
        $page->assertActionHidden(TestAction::make(DeleteAction::class)->table($request))
            ->assertActionHidden(TestAction::make(DeleteBulkAction::class)->table()->bulk());
    }
})->with([
    'secretary' => [UserRole::Secretary, false],
    'admin' => [UserRole::Admin, true],
    'super admin' => [UserRole::SuperAdmin, true],
]);
