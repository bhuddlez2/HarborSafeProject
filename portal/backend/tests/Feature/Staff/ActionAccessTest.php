<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Content\Resources\Events\EventResource;
use App\Filament\Clusters\Content\Resources\Newsletters\NewsletterResource;
use App\Filament\Clusters\FormOptions\Resources\Counties\CountyResource;
use App\Filament\Clusters\FormOptions\Resources\Resources\ResourceTypeResource;
use App\Filament\Clusters\FormOptions\Resources\Services\ServiceResource;
use App\Filament\Clusters\Logs\Resources\AccessLog\AccessLogResource;
use App\Filament\Clusters\Logs\Resources\AssessmentEdits\AssessmentEditResource;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

/*
What each screen in the staff panel lets anyone DO - the buttons, not just
who may open it (that is PanelAccessTest).

Filament asks a screen before every built-in button (Create, View, Edit,
Delete, bulk delete, restore, replicate, reorder), and by default answers
"allowed" when nothing says otherwise. So a Delete button added to a
read-only screen would have worked for everyone who could open it. Now every
screen lists what it allows (App\Filament\Concerns\AllowsOnlyListedActions,
or the hand-written rules on Accounts, Officers, Agencies and the
law-enforcement assessment policy), and the panel is in strict mode, so a
screen with no rules at all is an error rather than a yes.

screenActions() is the list, written out. Adding a screen, or letting one do
more, means changing it here on purpose.
*/

afterEach(fn () => cleanupStaffData());

// Everything a screen could be asked. The first group needs no record.
const LIST_ACTIONS = ['viewAny', 'create', 'deleteAny', 'forceDeleteAny', 'restoreAny', 'reorder'];
const ROW_ACTIONS = ['view', 'update', 'delete', 'forceDelete', 'restore', 'replicate'];

// The most each screen ever allows, for anyone. Who gets it within that is
// each screen's own rule (canAccess() and, for some, the row).
function screenActions(): array
{
    return [
        // Content: add, edit and delete. Categories and form options one at a
        // time only, and only while nothing uses them.
        EventResource::class => ['viewAny', 'create', 'update', 'delete', 'deleteAny'],
        NewsletterResource::class => ['viewAny', 'create', 'update', 'delete', 'deleteAny'],
        EventCategoryResource::class => ['viewAny', 'create', 'update', 'delete'],
        ServiceResource::class => ['viewAny', 'create', 'update', 'delete'],
        ResourceTypeResource::class => ['viewAny', 'create', 'update', 'delete'],
        CountyResource::class => ['viewAny', 'create', 'update', 'delete'],
        // Statements from the public: never edited. Delete is for spam (and a
        // mistyped staff entry); create is staff typing in a feedback form.
        ResourceRequestResource::class => ['viewAny', 'view', 'delete', 'deleteAny'],
        ServiceFeedbackResource::class => ['viewAny', 'view', 'create', 'delete', 'deleteAny'],
        // A safety record: view only.
        CivilianAssessmentResource::class => ['viewAny', 'view'],
        // Updates go through the follow-up page's own action, never Edit.
        FollowUpResource::class => ['viewAny', 'view'],
        // Append-only logs.
        AccessLogResource::class => ['viewAny'],
        AssessmentEditResource::class => ['viewAny', 'view'],
        // Accounts and agencies are deactivated or kept, never deleted.
        StaffAccountResource::class => ['viewAny', 'create', 'view', 'update'],
        OfficerAccountResource::class => ['viewAny', 'create', 'view', 'update'],
        AgencyResource::class => ['viewAny', 'create', 'view', 'update'],
        // LawEnforcementAssessmentPolicy: view and edit (with a reason, through
        // AssessmentEditor). Submitted only through the wizard, never deleted.
        AssessmentReviewResource::class => ['viewAny', 'view', 'update'],
        LawEnforcementAssessmentResource::class => ['viewAny', 'view', 'update'],
    ];
}

// Screens whose rules never depend on the row, so an unsaved record shows
// what a real one would get.
function rowIndependentScreens(): array
{
    return [
        EventResource::class, NewsletterResource::class, EventCategoryResource::class,
        ServiceResource::class, ResourceTypeResource::class, CountyResource::class,
        ResourceRequestResource::class, ServiceFeedbackResource::class,
        CivilianAssessmentResource::class, AgencyResource::class,
    ];
}

// The two assessment screens share a policy, which answers for both screens
// alike; each screen's canAccess() keeps the other's users out.
function policyScreens(): array
{
    return [AssessmentReviewResource::class, LawEnforcementAssessmentResource::class];
}

/** @param class-string<Resource> $screen */
function screenAllows(string $screen, string $action): bool
{
    $record = in_array($action, ROW_ACTIONS, true) ? new ($screen::getModel()) : null;

    return $screen::getAuthorizationResponse($action, $record)->allowed();
}

// One account per role; police admins and officers with an agency, so the
// screens that need one (Officers, Logs) are open to them.
function everyRole(): array
{
    $agency = pestAgency('Action access agency');

    return [
        'secretary' => staffUser(UserRole::Secretary),
        'admin' => staffUser(UserRole::Admin),
        'super_admin' => staffUser(UserRole::SuperAdmin),
        'police_admin' => pestAgencyMember(UserRole::PoliceAdmin, $agency),
        'law_enforcement' => pestAgencyMember(UserRole::LawEnforcement, $agency),
    ];
}

test('every screen in the panel is in the list', function () {
    $registered = Filament::getPanel('staff')->getResources();
    $listed = array_keys(screenActions());

    sort($registered);
    sort($listed);

    expect($listed)->toBe($registered, 'A screen was added or removed: list what it allows in screenActions().');
});

test('no screen allows an action outside its list, for any role', function () {
    foreach (everyRole() as $roleName => $user) {
        $this->actingAs($user);

        foreach (screenActions() as $screen => $allowed) {
            foreach ([...LIST_ACTIONS, ...ROW_ACTIONS] as $action) {
                if (in_array($action, $allowed, true)) {
                    continue;
                }

                expect(screenAllows($screen, $action))->toBeFalse(
                    sprintf('%s lets %s %s', class_basename($screen), $roleName, $action),
                );
            }
        }
    }
});

test('a screen refuses everything to someone who cannot open it', function () {
    foreach (everyRole() as $roleName => $user) {
        $this->actingAs($user);

        foreach (array_keys(screenActions()) as $screen) {
            if (in_array($screen, policyScreens(), true) || $screen::canAccess()) {
                continue;
            }

            foreach ([...LIST_ACTIONS, ...ROW_ACTIONS] as $action) {
                expect(screenAllows($screen, $action))->toBeFalse(
                    sprintf('%s lets %s %s without opening it', class_basename($screen), $roleName, $action),
                );
            }
        }
    }
});

test('someone who can open a screen gets what it lists', function () {
    foreach (everyRole() as $roleName => $user) {
        $this->actingAs($user);

        foreach (screenActions() as $screen => $allowed) {
            if (! $screen::canAccess()) {
                continue;
            }

            foreach ($allowed as $action) {
                $isRowAction = in_array($action, ROW_ACTIONS, true);

                if ($isRowAction && ! in_array($screen, rowIndependentScreens(), true)) {
                    continue; // checked on real rows in each screen's own tests
                }

                expect(screenAllows($screen, $action))->toBeTrue(
                    sprintf('%s refuses %s %s', class_basename($screen), $roleName, $action),
                );
            }
        }
    }
});

test('an inactive account is refused every action on every screen', function (UserRole $role) {
    $this->actingAs(staffUser($role, isActive: false));

    foreach (array_keys(screenActions()) as $screen) {
        foreach ([...LIST_ACTIONS, ...ROW_ACTIONS] as $action) {
            expect(screenAllows($screen, $action))->toBeFalse(
                sprintf('%s lets an inactive %s %s', class_basename($screen), $role->value, $action),
            );
        }
    }
})->with([
    'secretary' => UserRole::Secretary,
    'admin' => UserRole::Admin,
    'super_admin' => UserRole::SuperAdmin,
    'police_admin' => UserRole::PoliceAdmin,
    'law_enforcement' => UserRole::LawEnforcement,
]);

test('a screen with no rules is an error, not a yes', function () {
    expect(Filament::getPanel('staff')->isAuthorizationStrict())->toBeTrue();

    $this->actingAs(staffUser(UserRole::Admin));

    // A screen someone might add later and forget to give rules: Event has
    // no policy, so without strict mode Filament would allow every action.
    $forgotten = new class extends Resource
    {
        protected static ?string $model = Event::class;
    };

    expect(fn () => $forgotten::getAuthorizationResponse('delete', new Event))
        ->toThrow(LogicException::class);
});
