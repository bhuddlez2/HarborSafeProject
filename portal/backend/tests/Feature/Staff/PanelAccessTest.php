<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Content\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Content\Resources\Events\EventResource;
use App\Filament\Clusters\Content\Resources\Newsletters\NewsletterResource;
use App\Filament\Clusters\FormOptions\Resources\Counties\CountyResource;
use App\Filament\Clusters\FormOptions\Resources\Resources\ResourceTypeResource;
use App\Filament\Clusters\FormOptions\Resources\Services\ServiceResource;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Filament\Clusters\Submissions\SubmissionsCluster;
use App\Filament\Pages\MyAssessments;
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\Police;
use App\Filament\Pages\SearchRecords;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Filament\StaffLanding;

/*
Section 5 of Filament_CMS_Design.md as executable assertions - the access
matrix, one row per role.

This file exists because the matrix had drifted: Police, MyAssessments and
SearchRecords all granted UserRole::Admin, which put the whole Officer Portal
in an admin's sidebar and made content management look like a copy of it. The
matrix said otherwise, but nothing checked.

The point of the expected() table below is that it is the matrix, written out.
A component appearing in the wrong role's list is a failing test, not a code
review catch.
*/

afterEach(fn () => cleanupStaffData());

// Every gateable component in the panel. A component NOT listed in a role's
// expected set must refuse that role.
function allStaffComponents(): array
{
    return [
        'content.cluster' => ContentCluster::class,
        'content.events' => EventResource::class,
        'content.newsletters' => NewsletterResource::class,
        'content.categories' => EventCategoryResource::class,
        'submissions.cluster' => SubmissionsCluster::class,
        'submissions.service-feedback' => ServiceFeedbackResource::class,
        'submissions.resource-requests' => ResourceRequestResource::class,
        'form-options.services' => ServiceResource::class,
        'form-options.resource-types' => ResourceTypeResource::class,
        'form-options.counties' => CountyResource::class,
        'assessment-review' => AssessmentReviewResource::class,
        'civilian-assessments' => CivilianAssessmentResource::class,
        'police.home' => Police::class,
        'police.new-assessment' => NewAssessment::class,
        'police.my-assessments' => MyAssessments::class,
        'police.search-records' => SearchRecords::class,
    ];
}

dataset('role matrix', [
    'secretary' => [
        UserRole::Secretary,
        [
            'content.cluster', 'content.events', 'content.newsletters', 'content.categories',
            'submissions.cluster', 'submissions.service-feedback', 'submissions.resource-requests',
            'form-options.services', 'form-options.resource-types', 'form-options.counties',
        ],
    ],
    // Admin gets everything on the content side plus the all-submissions
    // assessment view - but NOT the officer portal. Rule 2's sibling: admin is
    // not a superset of every role.
    'admin' => [
        UserRole::Admin,
        [
            'content.cluster', 'content.events', 'content.newsletters', 'content.categories',
            'submissions.cluster', 'submissions.service-feedback', 'submissions.resource-requests',
            'form-options.services', 'form-options.resource-types', 'form-options.counties',
            'assessment-review',
            // Section 5 gives admin "Civilian assessments: view" and gives it
            // to nobody else - not even police_admin, whose row covers
            // law-enforcement submissions only.
            'civilian-assessments',
        ],
    ],
    // Views all law-enforcement submissions, provisions officers, edits
    // nothing. No content access at all.
    'police_admin' => [
        UserRole::PoliceAdmin,
        [
            'assessment-review',
            'police.home', 'police.my-assessments', 'police.search-records',
        ],
    ],
    // The only role that may submit an assessment, and the only one with no
    // reach beyond the officer portal.
    'law_enforcement' => [
        UserRole::LawEnforcement,
        [
            'police.home', 'police.new-assessment',
            'police.my-assessments', 'police.search-records',
        ],
    ],
]);

test('each role can reach exactly its row of the access matrix', function (UserRole $role, array $allowed) {
    $this->actingAs(staffUser($role));

    foreach (allStaffComponents() as $key => $component) {
        $shouldAccess = in_array($key, $allowed, true);

        expect($component::canAccess())
            ->toBe(
                $shouldAccess,
                sprintf(
                    '%s should %s be reachable by %s',
                    $key,
                    $shouldAccess ? '' : 'NOT',
                    $role->value,
                ),
            );
    }
})->with('role matrix');

test('an inactive account is refused everything, whatever its role', function (UserRole $role) {
    $this->actingAs(staffUser($role, isActive: false));

    foreach (allStaffComponents() as $key => $component) {
        expect($component::canAccess())->toBeFalse("{$key} must refuse an inactive {$role->value}");
    }

    // And the panel door itself, which is the gate that matters most.
    expect(staffUser($role, isActive: false)->canAccessPanel(filament()->getPanel('staff')))
        ->toBeFalse();
})->with([
    'admin' => [UserRole::Admin],
    'secretary' => [UserRole::Secretary],
    'police_admin' => [UserRole::PoliceAdmin],
    'law_enforcement' => [UserRole::LawEnforcement],
]);

/*
Rule 1 of section 5: only the submitting officer may ever edit an assessment,
so the all-submissions review page offers no way to change one.

Asserted against the resource's own declaration rather than by scraping the
rendered page, because that is where the mistake would be made.
*/
test('the assessment review page offers no way to alter a submission', function () {
    $table = AssessmentReviewResource::table(
        Filament\Tables\Table::make(new ListAssessmentReviewStub),
    );

    $actionNames = collect($table->getRecordActions())
        ->map(fn ($action) => $action->getName())
        ->all();

    expect($actionNames)->toBe(['view'])
        ->and($table->getToolbarActions())->toBe([]);
});

test('the officer assessment wizard stays officers-only', function () {
    // Explicit, because this is the one place a submission is created and
    // widening it would let a non-officer write law_enforcement_assessment.
    $this->actingAs(staffUser(UserRole::PoliceAdmin));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::Admin));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::Secretary));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::LawEnforcement));
    expect(NewAssessment::canAccess())->toBeTrue();
});

/*
The routing complaint that started this work: everyone signs in at the same
screen, and where they land afterwards depends on their role - police to the
officer portal, admin and secretary to content management, which must not be a
/staff/police/* address.
*/
test('each role lands on its own area after signing in', function () {
    $contentUrl = ContentCluster::getUrl();
    $policeUrl = Police::getUrl();

    expect(StaffLanding::urlFor(staffUser(UserRole::Admin)))->toBe($contentUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::Secretary)))->toBe($contentUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::LawEnforcement)))->toBe($policeUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::PoliceAdmin)))->toBe($policeUrl);

    // The two areas are genuinely separate addresses, not one nested in the
    // other. This is what was wrong before.
    expect($contentUrl)->toContain('/staff/content')
        ->and($contentUrl)->not->toContain('/police')
        ->and($policeUrl)->toContain('/staff/police');
});

test('an inactive account has no landing page at all', function () {
    expect(StaffLanding::urlFor(staffUser(UserRole::Admin, isActive: false)))->toBeNull()
        ->and(StaffLanding::urlFor(null))->toBeNull();
});

// Filament's Table::make() needs a Livewire component for context; the table
// definition under test never touches it.
class ListAssessmentReviewStub extends App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview {}
