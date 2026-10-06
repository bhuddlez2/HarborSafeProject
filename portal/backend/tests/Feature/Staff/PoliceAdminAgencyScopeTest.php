<?php

use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use App\Filament\Resources\OfficerAccounts\Pages\ListOfficerAccounts;
use App\Models\Agency;
use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Section 5 rule 5 of Filament_CMS_Design.md: police admins are scoped to their
own agency. A police admin sees only the officers, assessments and change logs
of the agency in their law_enforcement_agents row (User::agencyId()), through
LawEnforcementAssessment::visibleTo(); with no agency, nothing. My Assessments
is officers only, and admin sees every agency in Assessment review.

A failure here means the rule was broken, not that the test is wrong. The rule
was narrowed on purpose - police admins used to see every agency - and this
file exists so it cannot be quietly widened back.

Two agencies, each with a police admin and an officer, and one assessment per
officer. Everything is tagged with STAFF_TEST_PREFIX and swept in afterEach.
*/

afterEach(function () {
    // Children first: law_enforcement_assessment.submitted_by does not
    // cascade, so the assessments must go before cleanupStaffData() deletes
    // the users. Agent rows cascade with their users.
    $portal = DB::connection('Portal');
    $docs = $portal->table('law_enforcement_assessment')
        ->where('VictimFirstName', 'like', STAFF_TEST_PREFIX.'%')
        ->pluck('AssessmentDocID');

    $portal->table('law_enforcement_assessment')
        ->where('VictimFirstName', 'like', STAFF_TEST_PREFIX.'%')
        ->delete();

    $portal->table('_assessment_answers')->whereIn('AssessmentDocID', $docs)->delete();

    cleanupStaffData();

    // After the users, so no agent row still points at these agencies.
    $portal->table('agencies')->where('name', 'like', STAFF_TEST_PREFIX.'%')->delete();
});

function pestAgency(string $name): Agency
{
    return Agency::withoutTimestamps(
        fn (): Agency => Agency::create(['name' => staffRunToken().' '.$name]),
    );
}

// A staff account with a law_enforcement_agents row in $agency - the row
// User::agencyId() reads, for police admins as well as officers.
function pestAgencyMember(UserRole $role, ?Agency $agency): User
{
    $user = staffUser($role);

    LawEnforcementAgent::withoutTimestamps(fn () => LawEnforcementAgent::create([
        'user_id' => $user->getKey(),
        'badge_number' => 'PEST-'.$user->getKey(),
        'agency_id' => $agency?->getKey(),
    ]));

    return $user;
}

function pestOfficerAssessment(User $officer, string $victim): LawEnforcementAssessment
{
    $answers = AssessmentAnswers::create(collect(range(1, 11))
        ->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => $id === 1])
        ->all());

    return LawEnforcementAssessment::create([
        'submitted_by' => $officer->getKey(),
        'VictimFirstName' => STAFF_TEST_PREFIX.$victim,
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'AssessmentDocID' => $answers->getKey(),
    ]);
}

// Two agencies, A and B, each with a police admin, an officer and one
// assessment by that officer.
function pestTwoAgencies(): object
{
    $agencyA = pestAgency('Agency A');
    $agencyB = pestAgency('Agency B');

    $officerA = pestAgencyMember(UserRole::LawEnforcement, $agencyA);
    $officerB = pestAgencyMember(UserRole::LawEnforcement, $agencyB);

    return (object) [
        'policeAdminA' => pestAgencyMember(UserRole::PoliceAdmin, $agencyA),
        'policeAdminB' => pestAgencyMember(UserRole::PoliceAdmin, $agencyB),
        'officerA' => $officerA,
        'officerB' => $officerB,
        'assessmentA' => pestOfficerAssessment($officerA, 'VictimA'),
        'assessmentB' => pestOfficerAssessment($officerB, 'VictimB'),
    ];
}

test('a police admin reviews only their own agency\'s assessments', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->policeAdminA);

    $list = Livewire::test(ListAssessmentReview::class)
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCanNotSeeTableRecords([$w->assessmentB]);

    // Assessment review has no view page - View is a modal on the table - so
    // "the other agency's record by key" is the table's record lookup, which
    // runs through the scoped query, plus the policy behind it.
    expect($list->instance()->getTableRecord($w->assessmentB->getKey()))->toBeNull()
        ->and($list->instance()->getTableRecord($w->assessmentA->getKey()))->not->toBeNull()
        ->and($w->policeAdminA->can('view', $w->assessmentB))->toBeFalse()
        ->and($w->policeAdminA->can('view', $w->assessmentA))->toBeTrue();

    // And the mirror image, so the scope is not just "agency A".
    $this->actingAs($w->policeAdminB);

    Livewire::test(ListAssessmentReview::class)
        ->assertCanSeeTableRecords([$w->assessmentB])
        ->assertCanNotSeeTableRecords([$w->assessmentA]);
});

test('a police admin is only offered their own agency\'s officers in the review filter', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->policeAdminA);

    // The table's real Officer filter, searched the way its dropdown is. Both
    // officers share the name staffUser() gives them, so compare by key.
    $field = Livewire::test(ListAssessmentReview::class)
        ->instance()
        ->getTableFiltersForm()
        ->getComponent(fn ($component): bool => $component instanceof Select, withHidden: true);

    expect(array_keys($field->getSearchResults('Pest')))
        ->toContain($w->officerA->getKey())
        ->not->toContain($w->officerB->getKey());
});

test('a police admin with no agency sees nothing', function () {
    $w = pestTwoAgencies();
    $orphan = pestAgencyMember(UserRole::PoliceAdmin, null);

    $this->actingAs($orphan);

    Livewire::test(ListAssessmentReview::class)
        ->assertCanNotSeeTableRecords([$w->assessmentA, $w->assessmentB])
        ->assertCountTableRecords(0);

    expect($orphan->can('view', $w->assessmentA))->toBeFalse()
        ->and($orphan->can('view', $w->assessmentB))->toBeFalse();

    // Same for a police admin with no law_enforcement_agents row at all.
    $rowless = staffUser(UserRole::PoliceAdmin);

    expect(LawEnforcementAssessment::visibleTo($rowless)->count())->toBe(0);
});

test('a police admin is refused My Assessments', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->policeAdminA);

    expect(LawEnforcementAssessmentResource::canAccess())->toBeFalse();

    // My Assessments has no view page - View is a modal on this list - so
    // refusing the list refuses every record, their own agency's included.
    $this->get(LawEnforcementAssessmentResource::getUrl('index'))->assertForbidden();
});

test('an officer sees only their own assessments and is refused Assessment review', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->officerA);

    $list = Livewire::test(ListLawEnforcementAssessments::class)
        ->assertCanSeeTableRecords([$w->assessmentA])
        ->assertCanNotSeeTableRecords([$w->assessmentB]);

    // View and Edit are modals on the list, so "someone else's record by key"
    // is the table's record lookup, which runs through the scoped query.
    expect($list->instance()->getTableRecord($w->assessmentA->getKey()))->not->toBeNull()
        ->and($list->instance()->getTableRecord($w->assessmentB->getKey()))->toBeNull()
        ->and($w->officerA->can('view', $w->assessmentB))->toBeFalse();

    expect(AssessmentReviewResource::canAccess())->toBeFalse();

    $this->get(AssessmentReviewResource::getUrl('index'))->assertForbidden();
});

test('an admin reviews every agency\'s assessments', function () {
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);

    $this->actingAs($admin);

    Livewire::test(ListAssessmentReview::class)
        ->assertCanSeeTableRecords([$w->assessmentA, $w->assessmentB]);

    expect($admin->can('view', $w->assessmentA))->toBeTrue()
        ->and($admin->can('view', $w->assessmentB))->toBeTrue();
});

test('a police admin lists only their own agency\'s officers', function () {
    $w = pestTwoAgencies();

    $this->actingAs($w->policeAdminA);

    // Not the other agency's officer, and not police admins - either agency's,
    // including the signed-in one.
    Livewire::test(ListOfficerAccounts::class)
        ->assertCanSeeTableRecords([$w->officerA])
        ->assertCanNotSeeTableRecords([$w->officerB, $w->policeAdminA, $w->policeAdminB]);
});

test('officer accounts open only to a police admin with an agency', function () {
    $w = pestTwoAgencies();

    $cases = [
        'police admin with an agency' => [$w->policeAdminA, true],
        'police admin with no agency' => [pestAgencyMember(UserRole::PoliceAdmin, null), false],
        // Admin creates secretaries, never officers (section 5 rule 2).
        'admin' => [staffUser(UserRole::Admin), false],
        'secretary' => [staffUser(UserRole::Secretary), false],
        'officer' => [$w->officerA, false],
    ];

    foreach ($cases as $label => [$user, $expected]) {
        $this->actingAs($user);

        expect(OfficerAccountResource::canAccess())->toBe($expected, "officer accounts for: {$label}")
            ->and(OfficerAccountResource::canCreate())->toBe($expected, "creating officers for: {$label}");
    }
});

test('nobody but the submitting officer may edit an assessment', function () {
    $w = pestTwoAgencies();

    expect(staffUser(UserRole::Admin)->can('update', $w->assessmentA))->toBeFalse()
        ->and($w->policeAdminA->can('update', $w->assessmentA))->toBeFalse()
        ->and(staffUser(UserRole::Secretary)->can('update', $w->assessmentA))->toBeFalse()
        ->and($w->officerB->can('update', $w->assessmentA))->toBeFalse()
        ->and($w->officerA->can('update', $w->assessmentA))->toBeTrue();
});
