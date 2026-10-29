<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Analytics\AnalyticsCluster;
use App\Filament\Clusters\Analytics\Pages\CivilianAssessmentAnalytics;
use App\Filament\Clusters\Analytics\Pages\FeedbackAnalytics;
use App\Filament\Clusters\Analytics\Pages\FollowUpAnalytics;
use App\Filament\Clusters\Analytics\Pages\OfficerAssessmentAnalytics;
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
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\AgencyAnalytics;
use App\Filament\Pages\Police;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\Logs\LogResource;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use App\Filament\StaffLanding;

/*
Section 5 of Filament_CMS_Design.md as executable assertions - the access
matrix, one row per role.

This file exists because the matrix had drifted: Police, MyAssessments and
SearchRecords (since removed) all granted UserRole::Admin, which put the whole Officer Portal
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
        'accounts' => StaffAccountResource::class,
        'agencies' => AgencyResource::class,
        'assessment-review' => AssessmentReviewResource::class,
        'civilian-assessments' => CivilianAssessmentResource::class,
        'police.home' => Police::class,
        'police.new-assessment' => NewAssessment::class,
        'police.my-assessments' => LawEnforcementAssessmentResource::class,
        'follow-ups' => FollowUpResource::class,
        'logs' => LogResource::class,
        'analytics.cluster' => AnalyticsCluster::class,
        'analytics.officer-assessments' => OfficerAssessmentAnalytics::class,
        'analytics.civilian-assessments' => CivilianAssessmentAnalytics::class,
        'analytics.feedback' => FeedbackAnalytics::class,
        'analytics.follow-ups' => FollowUpAnalytics::class,
        // Police admins with an agency only (see the test below), so in no
        // role's row here: staffUser() gives no agency.
        'police.analytics' => AgencyAnalytics::class,
    ];
}

dataset('role matrix', [
    'secretary' => [
        UserRole::Secretary,
        [
            'content.cluster', 'content.events', 'content.newsletters', 'content.categories',
            'submissions.cluster', 'submissions.service-feedback', 'submissions.resource-requests',
            'form-options.services', 'form-options.resource-types', 'form-options.counties',
            // Resource requests only; the queue never shows a secretary an
            // assessment (FollowUp::visibleTo()).
            'follow-ups',
            // Analytics: the feedback and resource request tab only.
            'analytics.cluster', 'analytics.feedback',
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
            // Creates secretaries, and nothing else (section 5).
            'accounts',
            // Follow-up on every assessment and resource request.
            'follow-ups',
            // Logs: every view, PDF download and edit, in one list. Every
            // row; read-only.
            'logs',
            // Analytics: every tab, every agency together.
            'analytics.cluster', 'analytics.officer-assessments', 'analytics.civilian-assessments',
            'analytics.feedback', 'analytics.follow-ups',
        ],
    ],
    // Admin's row plus Agencies (team plan task 15). Still no Officer Portal:
    // super admin never submits or edits an assessment, so New assessment and
    // My Assessments stay closed.
    'super_admin' => [
        UserRole::SuperAdmin,
        [
            'content.cluster', 'content.events', 'content.newsletters', 'content.categories',
            'submissions.cluster', 'submissions.service-feedback', 'submissions.resource-requests',
            'form-options.services', 'form-options.resource-types', 'form-options.counties',
            'assessment-review',
            'civilian-assessments',
            // Admins, secretaries, police admins and officers; never another
            // super admin (StaffAccountResource::manageableRoles()).
            'accounts',
            'agencies',
            'follow-ups',
            'logs',
            'analytics.cluster', 'analytics.officer-assessments', 'analytics.civilian-assessments',
            'analytics.feedback', 'analytics.follow-ups',
        ],
    ],
    // Reviews its own agency's law-enforcement submissions in Assessment
    // review (rule 5), provisions officers, edits nothing. Not My Assessments,
    // which is officers only. No content access at all. staffUser() gives no
    // agency, so neither Officers nor Logs is here; with an agency Logs
    // opens (see the test below).
    'police_admin' => [
        UserRole::PoliceAdmin,
        [
            'assessment-review',
            'police.home',
        ],
    ],
    // The only role that may submit an assessment, and the only one with no
    // reach beyond the officer portal.
    'law_enforcement' => [
        UserRole::LawEnforcement,
        [
            'police.home', 'police.new-assessment',
            'police.my-assessments',
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

// Logs needs an agency for a police admin, like Officers: it shows only that
// agency's rows (LogEntry::visibleTo()).
test('a police admin reaches Logs only with an agency', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Access agency')));
    expect(LogResource::canAccess())->toBeTrue();

    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, null));
    expect(LogResource::canAccess())->toBeFalse();
});

// Analytics for a police admin's own agency (#67) needs an agency, like Logs.
test('a police admin reaches their Analytics only with an agency', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Analytics agency')));
    expect(AgencyAnalytics::canAccess())->toBeTrue()
        ->and(OfficerAssessmentAnalytics::canAccess())->toBeFalse();

    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, null));
    expect(AgencyAnalytics::canAccess())->toBeFalse();
});

test('an inactive account is refused everything, whatever its role', function (UserRole $role) {
    $this->actingAs(staffUser($role, isActive: false));

    foreach (allStaffComponents() as $key => $component) {
        expect($component::canAccess())->toBeFalse("{$key} must refuse an inactive {$role->value}");
    }

    // And the panel door itself, which is the gate that matters most.
    expect(staffUser($role, isActive: false)->canAccessPanel(filament()->getPanel('staff')))
        ->toBeFalse();
})->with([
    'super_admin' => [UserRole::SuperAdmin],
    'admin' => [UserRole::Admin],
    'secretary' => [UserRole::Secretary],
    'police_admin' => [UserRole::PoliceAdmin],
    'law_enforcement' => [UserRole::LawEnforcement],
]);

/*
Rule 1 of section 5: an assessment is edited only by its submitting officer or
an active police admin of the submitter's agency. The review page's only
write is the shared, policy-gated Edit (hidden for admins - see
PoliceAdminAssessmentsTest); nothing else can change or remove a submission.

Asserted against the resource's own declaration rather than by scraping the
rendered page, because that is where the mistake would be made.
*/
test('the assessment review page offers no way to alter a submission but the policy-gated Edit', function () {
    $table = AssessmentReviewResource::table(
        Filament\Tables\Table::make(new ListAssessmentReviewStub),
    );

    $actionNames = collect($table->getRecordActions())
        ->map(fn ($action) => $action->getName())
        ->all();

    // The change log pop-up, the record view, and the policy-gated Edit.
    expect($actionNames)->toBe(['changeLog', 'view', 'edit'])
        ->and($table->getToolbarActions())->toBe([]);
});

test('the assessment wizard is for officers and police admins with an agency only', function () {
    // Explicit, because this is the one place a submission is created.
    // Police admins submit their own (team plan task 7) - but only with an
    // agency, which is what puts their submissions in their own scope.
    $this->actingAs(staffUser(UserRole::PoliceAdmin));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Wizard PD')));
    expect(NewAssessment::canAccess())->toBeTrue();

    $this->actingAs(staffUser(UserRole::Admin));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::SuperAdmin));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::Secretary));
    expect(NewAssessment::canAccess())->toBeFalse();

    $this->actingAs(staffUser(UserRole::LawEnforcement));
    expect(NewAssessment::canAccess())->toBeTrue();
});

/*
The routing complaint that started this work: everyone signs in at the same
screen, and where they land afterwards depends on their role - police to the
officer portal, Harbor Safe staff (super admin, admin, secretary) to the
Follow-ups queue, which must not be a /police/* address.
*/
test('each role lands on its own area after signing in', function () {
    $followUpsUrl = FollowUpResource::getUrl();
    $policeUrl = Police::getUrl();

    expect(StaffLanding::urlFor(staffUser(UserRole::SuperAdmin)))->toBe($followUpsUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::Admin)))->toBe($followUpsUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::Secretary)))->toBe($followUpsUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::LawEnforcement)))->toBe($policeUrl)
        ->and(StaffLanding::urlFor(staffUser(UserRole::PoliceAdmin)))->toBe($policeUrl);

    // The two areas are genuinely separate addresses, not one nested in the
    // other. This is what was wrong before.
    expect($followUpsUrl)->toContain('/follow-ups')
        ->and($followUpsUrl)->not->toContain('/police')
        ->and($policeUrl)->toContain('/police');
});

test('an inactive account has no landing page at all', function () {
    expect(StaffLanding::urlFor(staffUser(UserRole::Admin, isActive: false)))->toBeNull()
        ->and(StaffLanding::urlFor(null))->toBeNull();
});

// Filament's Table::make() needs a Livewire component for context; the table
// definition under test never touches it.
class ListAssessmentReviewStub extends App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview {}
