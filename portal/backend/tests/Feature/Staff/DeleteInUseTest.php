<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\ResourceRequestForm;
use App\Models\Resource as ResourceType;
use App\Models\Service;
use App\Models\ServiceFeedback;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
Burchfield B54 (2026-10-29): an option or category that records use keeps its
Delete button, greyed out with the reason on hover, and the server refuses
the delete too. An unused one can still be deleted.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

function assertDeleteGreyedOut(string $page, $used, $unused, string $reason): void
{
    test()->actingAs(staffUser(UserRole::Admin));

    Livewire::test($page)
        ->assertActionVisible(TestAction::make(DeleteAction::class)->table($used))
        ->assertActionDisabled(TestAction::make(DeleteAction::class)->table($used))
        ->assertActionExists(
            TestAction::make(DeleteAction::class)->table($used),
            fn (Action $button): bool => $button->getTooltip() === $reason,
        )
        ->callAction(TestAction::make(DeleteAction::class)->table($used))
        ->callAction(TestAction::make(DeleteAction::class)->table($unused));

    expect($used->fresh())->not->toBeNull()
        ->and($unused->fresh())->toBeNull();
}

test('a county, resource type or service that a form uses greys out Delete', function () {
    $county = County::find(publicFormsMakeCounty('Pest Used County'));
    $resource = ResourceType::find(publicFormsMakeResource('Pest Used Resource'));
    $service = Service::find(publicFormsMakeService('Pest Used Service'));

    $request = (new ResourceRequestForm)->forceFill([
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => publicFormsEmail(),
        'SafePhoneNumber' => '423-555-0177',
        'CountyID' => $county->getKey(),
        'Message' => 'Help please.',
        'SubmissionDate' => '2026-09-15 18:30:00',
    ]);
    $request->save();
    $request->resourceTypes()->attach($resource->getKey());

    (new ServiceFeedback)->forceFill([
        'ServiceID' => $service->getKey(),
        'Rating' => 2,
        'SubmissionDate' => '2026-09-15 18:30:00',
        'Source' => ServiceFeedback::SOURCE_ONLINE,
    ])->save();

    $reason = 'Can\'t delete: existing records use this option.';

    assertDeleteGreyedOut(ManageCounties::class, $county, County::find(publicFormsMakeCounty('Pest Spare County')), $reason);
    assertDeleteGreyedOut(ManageResourceTypes::class, $resource, ResourceType::find(publicFormsMakeResource('Pest Spare Resource')), $reason);
    assertDeleteGreyedOut(ManageServices::class, $service, Service::find(publicFormsMakeService('Pest Spare Service')), $reason);
});

test('an event category that an event uses greys out Delete', function () {
    $used = EventCategory::create(['Name' => staffRunToken().' Used Category']);
    $unused = EventCategory::create(['Name' => staffRunToken().' Spare Category']);

    Event::create([
        'title' => staffRunToken().' Category Event',
        'summary' => 'An event.',
        'starts_at' => '2026-10-22 22:00:00',
        'all_day' => false,
        'location_is_virtual' => false,
        'category_id' => $used->id,
        'is_published' => false,
    ]);

    assertDeleteGreyedOut(ManageEventCategories::class, $used, $unused, 'Can\'t delete: existing events use this category.');
});
