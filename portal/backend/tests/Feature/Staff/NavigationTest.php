<?php

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use Filament\Facades\Filament;

/*
The sidebar each role is shown, group by group and item by item, in order.

Police admins get one "Police Admin" group (App\Filament\PoliceAdminNavigation)
built partly from items other roles also see - Home, New Assessment,
Assessment Review, Logs, Account - so this pins both sides: the police admin's single group, and
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
        ['Police Admin' => ['Home', 'New Assessment', 'Assessment Review', 'Logs', 'Analytics', 'Staff Management', 'Account']],
    ],
    // New Assessment, Officers and Logs need an agency (their canAccess());
    // the rest of the group is unaffected.
    'police admin with no agency' => [
        fn () => pestAgencyMember(UserRole::PoliceAdmin, null),
        ['Police Admin' => ['Home', 'Assessment Review', 'Account']],
    ],
    'law enforcement' => [
        fn () => staffUser(UserRole::LawEnforcement),
        ['Officer Portal' => ['Home', 'New Assessment', 'My Assessments', 'Account']],
    ],
    // Admin's sidebar plus Agencies after Accounts.
    'super admin' => [
        fn () => staffUser(UserRole::SuperAdmin),
        [
            'Content Management' => ['Follow-Ups', 'Content', 'Submissions', 'Form Options', 'Accounts', 'Agencies'],
            'Assessments' => ['Assessment Review', 'Civilian Assessments', 'Logs'],
            'Reports' => ['Analytics'],
        ],
    ],
    // Follow-Ups is the day's work queue, first under Content Management
    // and the page these roles land on.
    'admin' => [
        fn () => staffUser(UserRole::Admin),
        [
            'Content Management' => ['Follow-Ups', 'Content', 'Submissions', 'Form Options', 'Accounts'],
            'Assessments' => ['Assessment Review', 'Civilian Assessments', 'Logs'],
            'Reports' => ['Analytics'],
        ],
    ],
    'secretary' => [
        fn () => staffUser(UserRole::Secretary),
        [
            'Content Management' => ['Follow-Ups', 'Content', 'Submissions', 'Form Options'],
            'Reports' => ['Analytics'],
        ],
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
        'New Assessment' => PoliceAdminNavigation::NEW_ASSESSMENT,
        'Assessment Review' => PoliceAdminNavigation::ASSESSMENT_REVIEW,
        'Logs' => PoliceAdminNavigation::LOGS,
        'Analytics' => PoliceAdminNavigation::ANALYTICS,
        'Staff Management' => PoliceAdminNavigation::OFFICERS,
        'Account' => PoliceAdminNavigation::ACCOUNT,
    ])
        ->and(array_values($sorts))->toBe([10, 20, 30, 40, 50, 60, 70]);
});

// Finishing Touches item 27: every sidebar group, sidebar item and cluster tab
// in title case. Each word (hyphenated parts too, "Follow-Ups") starts with a
// capital, except short joining words such as "and".
test('every sidebar label and cluster tab is in title case', function () {
    Filament::setCurrentPanel('staff');
    $panel = Filament::getCurrentPanel();

    $labels = collect($panel->getNavigationGroups())
        ->map(fn ($group): string => is_string($group) ? $group : (string) $group->getLabel());

    foreach ([...$panel->getResources(), ...$panel->getPages(), ...$panel->getClusters()] as $component) {
        $labels->push((string) $component::getNavigationLabel());
    }

    foreach ($panel->getClusters() as $cluster) {
        foreach ($cluster::getClusteredComponents() as $component) {
            $labels->push((string) $component::getNavigationLabel());
        }
    }

    $notTitleCase = $labels->unique()->reject(fn (string $label): bool => collect(preg_split('/[\s-]+/', $label))
        ->every(fn (string $word): bool => in_array($word, ['a', 'an', 'and', 'for', 'of', 'or', 'the', 'to'], true)
            || ctype_upper($word[0])))
        ->values()
        ->all();

    expect($notTitleCase)->toBe([]);
});
