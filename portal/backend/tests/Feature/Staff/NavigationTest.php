<?php

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use Filament\Facades\Filament;

/*
The sidebar each role is shown, group by group and item by item, in order.

Police admins get one "Police Admin" group (App\Filament\PoliceAdminNavigation)
built partly from items other roles also see - Home, Assessment review, Change
log, Account - so this pins both sides: the police admin's single group, and
every other role's sidebar exactly as it was before that group existed.

Grouping and order only. Who may open each item is PanelAccessTest's job.
*/

afterEach(fn () => cleanupStaffData());

// [group label => [item labels]], both in the order the sidebar shows them.
function pestSidebar(): array
{
    Filament::setCurrentPanel('staff');

    return collect(Filament::getNavigation())
        ->mapWithKeys(fn ($group): array => [
            (string) $group->getLabel() => collect($group->getItems())
                ->map(fn ($item): string => (string) $item->getLabel())
                ->values()
                ->all(),
        ])
        ->all();
}

test('each role sees exactly its own sidebar', function (Closure $makeUser, array $expected) {
    $this->actingAs($makeUser());

    expect(pestSidebar())->toBe($expected);
})->with([
    'police admin' => [
        fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Nav agency')),
        ['Police Admin' => ['Home', 'Assessment review', 'Change log', 'Officers', 'Account']],
    ],
    // Officers needs an agency (OfficerAccountResource::canAccess()); the
    // rest of the group is unaffected.
    'police admin with no agency' => [
        fn () => pestAgencyMember(UserRole::PoliceAdmin, null),
        ['Police Admin' => ['Home', 'Assessment review', 'Change log', 'Account']],
    ],
    'law enforcement' => [
        fn () => staffUser(UserRole::LawEnforcement),
        ['Officer Portal' => ['Home', 'New assessment', 'My Assessments', 'Account']],
    ],
    'admin' => [
        fn () => staffUser(UserRole::Admin),
        [
            'Content management' => ['Content', 'Submissions', 'Form options', 'Accounts'],
            'Assessments' => ['Assessment review', 'Civilian assessments', 'Change log'],
        ],
    ],
    'secretary' => [
        fn () => staffUser(UserRole::Secretary),
        ['Content management' => ['Content', 'Submissions', 'Form options']],
    ],
]);

test('the police admin group leaves gaps between positions', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Nav agency')));
    Filament::setCurrentPanel('staff');

    $sorts = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->mapWithKeys(fn ($item): array => [(string) $item->getLabel() => $item->getSort()])
        ->all();

    expect($sorts)->toBe([
        'Home' => PoliceAdminNavigation::HOME,
        'Assessment review' => PoliceAdminNavigation::ASSESSMENT_REVIEW,
        'Change log' => PoliceAdminNavigation::CHANGE_LOG,
        'Officers' => PoliceAdminNavigation::OFFICERS,
        'Account' => PoliceAdminNavigation::ACCOUNT,
    ])
        ->and(array_values($sorts))->toBe([10, 20, 30, 40, 50]);
});
