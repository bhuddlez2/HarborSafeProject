<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
Helpers for the /api/public/* feature tests in Feature/Public.

Those tests hit the real Feedback/FeedbackPublic connections - there's no
sqlite stand-in configured for the app's named connections, and the two are
separate MySQL sessions, so test transactions don't work here: rows written
on one connection wouldn't be visible to the other, and the controllers'
DB::disconnect('FeedbackPublic') would tear down an open transaction
mid-test anyway. So these tests commit for real and clean up after
themselves - everything they create is tagged, either by run token (written
into FirstName) or by the throwaway lookup row it references, and
cleanupPublicFormData() removes it in an afterEach hook.

Assertions read through the *Feedback* (full access) connection, never
FeedbackPublic: the restricted user deliberately has no SELECT on the
submission tables, which is itself asserted by the "cannot read back
submissions" tests.
*/

function publicFormsRunToken(): string
{
    static $token = null;

    return $token ??= 'PestRun' . Illuminate\Support\Str::random(8);
}

function publicFormsRegistry(): stdClass
{
    static $registry = null;

    if ($registry === null) {
        $registry = new stdClass();
        $registry->services = [];
        $registry->resources = [];
        $registry->counties = [];
    }

    return $registry;
}

function publicFormsMakeService(string $name = 'Pest Test Service'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('services')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->services[] = $id;

    return $id;
}

function publicFormsMakeResource(string $name = 'Pest Test Resource'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('resources')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->resources[] = $id;

    return $id;
}

function publicFormsMakeCounty(string $name = 'Pest Test County'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('counties')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->counties[] = $id;

    return $id;
}

function publicFormsEmail(): string
{
    return 'pest-' . Illuminate\Support\Str::random(12) . '@example.com';
}

function cleanupPublicFormData(): void
{
    $registry = publicFormsRegistry();
    $feedback = Illuminate\Support\Facades\DB::connection('Feedback');

    // Submissions first - resource_request_resource_types cascades off
    // resource_request_form, so deleting the parent clears the junction rows.
    $feedback->table('resource_request_form')->where('FirstName', publicFormsRunToken())->delete();

    if ($registry->services !== []) {
        $feedback->table('service_feedback')->whereIn('ServiceID', $registry->services)->delete();
        $feedback->table('services')->whereIn('id', $registry->services)->delete();
    }

    if ($registry->resources !== []) {
        $feedback->table('resources')->whereIn('id', $registry->resources)->delete();
    }

    if ($registry->counties !== []) {
        $feedback->table('counties')->whereIn('id', $registry->counties)->delete();
    }

    $registry->services = [];
    $registry->resources = [];
    $registry->counties = [];
}

/*
Helpers for the staff-panel tests in Feature/Staff.

Same approach as the public-form helpers above and for the same reason: there
is no sqlite stand-in for the app's named connections, so these tests use the
real Portal and Content databases and clean up after themselves rather than
leaning on a transaction.

Everything created is tagged with staffRunToken() - in the email for users, in
the title for events and newsletters, in the name for uploaded files - and
cleanupStaffData() removes it in an afterEach hook. Nothing here touches the
seeded *@harborsafe.test accounts.
*/

// The fixed prefix every staff-test artifact carries. Cleanup sweeps on THIS
// rather than on the per-run token, so a run that dies before afterEach - a
// PHP fatal kills the process outright - does not leave rows behind forever.
// It also means these tests must not be run in parallel against one database.
const STAFF_TEST_PREFIX = 'peststaff';

function staffRunToken(): string
{
    static $token = null;

    return $token ??= STAFF_TEST_PREFIX . Illuminate\Support\Str::random(8);
}

function staffUser(App\Enums\UserRole $role, bool $isActive = true): App\Models\User
{
    return App\Models\User::create([
        'first_name' => 'Pest',
        'last_name' => $role->value,
        'email' => staffRunToken() . '-' . $role->value . '-' . Illuminate\Support\Str::random(4) . '@pest.test',
        'password' => 'password',
        'role' => $role,
        'is_active' => $isActive,
    ]);
}

function cleanupStaffData(): void
{
    $token = STAFF_TEST_PREFIX;

    $content = Illuminate\Support\Facades\DB::connection('Content');

    // Events and newsletters first: they reference content_files, so clearing
    // the referencing rows before the files keeps the order safe if a foreign
    // key is ever added to those columns.
    $content->table('events')->where('title', 'like', $token . '%')->delete();
    $content->table('newsletters')->where('title', 'like', $token . '%')->delete();
    $content->table('content_files')->where('name', 'like', $token . '%')->delete();
    $content->table('event_categories')->where('Name', 'like', $token . '%')->delete();

    $portal = Illuminate\Support\Facades\DB::connection('Portal');

    // Law-enforcement assessments before users: submitted_by restricts
    // deletion of the submitting user. Then their answer rows, which the
    // assessment pointed at.
    $userIds = $portal->table('users')->where('email', 'like', $token . '%')->pluck('id');
    $answerIds = $portal->table('law_enforcement_assessment')
        ->whereIn('submitted_by', $userIds)
        ->pluck('AssessmentDocID');

    // The change log first: its rows point at the edits, which point at the
    // assessments. Query builder deletes, because the log models refuse
    // Eloquent deletes (AppendOnly). Edits by test users are swept too.
    $docIds = $portal->table('law_enforcement_assessment')->whereIn('submitted_by', $userIds)->pluck('DocumentID');
    $editIds = $portal->table('assessment_edits')
        ->where(fn ($q) => $q->whereIn('DocumentID', $docIds)->orWhereIn('ChangedBy', $userIds))
        ->pluck('EditID');

    $portal->table('assessment_change_log')->whereIn('EditID', $editIds)->delete();
    $portal->table('assessment_answer_change_log')->whereIn('EditID', $editIds)->delete();
    $portal->table('assessment_edits')->whereIn('EditID', $editIds)->delete();

    $portal->table('law_enforcement_assessment')->whereIn('submitted_by', $userIds)->delete();
    $portal->table('_assessment_answers')->whereIn('AssessmentDocID', $answerIds)->delete();

    // law_enforcement_agents rows cascade with their user.
    $portal->table('users')->where('email', 'like', $token . '%')->delete();
    $portal->table('agencies')->where('name', 'like', $token . '%')->delete();
}

/*
Agencies, their members and officer assessments, for the staff tests that
check the agency scope (PoliceAdminAgencyScopeTest, AssessmentFiltersTest).
Everything is tagged with STAFF_TEST_PREFIX: users and agencies through
staffRunToken(), assessments through their submitting user, and
cleanupStaffData() sweeps all of it.
*/

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

// $details overrides any of the default victim/offender fields.
function pestOfficerAssessment(User $officer, string $victim, array $details = []): LawEnforcementAssessment
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
        ...$details,
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
