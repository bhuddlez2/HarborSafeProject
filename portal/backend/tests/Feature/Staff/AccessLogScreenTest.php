<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Resources\AccessLog\AccessLogResource;
use App\Filament\Resources\AccessLog\Pages\ListAccessLog;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\ResourceRequestForm;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
The Access log screen (AccessLogResource, /access-log): every row of
assessment_access_log, for admins only, read-only, and naming each record by
its submitted time and id alone - never by anything in it.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function accessLogResourceRequest(): ResourceRequestForm
{
    $request = (new ResourceRequestForm)->forceFill([
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => publicFormsEmail(),
        'SafePhoneNumber' => '423-555-0177',
        'CountyID' => publicFormsMakeCounty(),
        'Message' => 'Pest access log request',
        'SubmissionDate' => '2026-09-15 18:30:00',
    ]);
    $request->save();

    return $request;
}

// One row of each kind: an officer's view and an admin's download of the
// officer assessment, then views of a civilian assessment and a resource
// request by the admin.
function accessLogFixture(): object
{
    $w = pestTwoAgencies();
    $admin = staffUser(UserRole::Admin);
    $civilian = pestCivilianAssessment();
    $request = accessLogResourceRequest();

    return (object) [
        ...(array) $w,
        'admin' => $admin,
        'civilian' => $civilian,
        'request' => $request,
        'officerView' => AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::View, $w->officerA),
        'adminDownload' => AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::Download, $admin),
        'civilianView' => AssessmentAccessLog::record($civilian, AssessmentAccess::View, $admin),
        'requestView' => AssessmentAccessLog::record($request, AssessmentAccess::View, $admin),
    ];
}

test('an admin opens the access log and sees its rows, and opening it logs nothing', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $before = AssessmentAccessLog::count();

    $this->get(AccessLogResource::getUrl('index'))->assertOk();

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView, $f->requestView])
        ->assertCanNotSeeTableRecords([$f->officerView])
        ->assertTableColumnFormattedStateSet('Action', 'Downloaded PDF', $f->adminDownload)
        ->assertTableColumnStateSet('record_type', 'Officer assessment', $f->adminDownload)
        ->assertTableColumnStateSet('record_type', 'Civilian assessment', $f->civilianView)
        ->assertTableColumnStateSet('record_type', 'Resource request', $f->requestView)
        // 18:30 UTC is 2:30 pm Eastern in September.
        ->assertTableColumnStateSet('record', 'Submitted Sep 15, 2026 2:30 pm · '.substr($f->request->FormID, 0, 8), $f->requestView);

    expect(AssessmentAccessLog::count())->toBe($before);
});

test('everyone but an active admin is kept out of the access log', function (Closure $makeUser) {
    $user = $makeUser();
    $this->actingAs($user);

    // canAccess() is also what keeps it out of the sidebar; NavigationTest
    // pins every role's sidebar.
    expect(AccessLogResource::canAccess())->toBeFalse();

    if ($user->is_active) {
        $this->get(AccessLogResource::getUrl('index', panel: 'staff'))->assertForbidden();
    }
})->with([
    'secretary' => [fn () => staffUser(UserRole::Secretary)],
    'police admin' => [fn () => staffUser(UserRole::PoliceAdmin)],
    'officer' => [fn () => staffUser(UserRole::LawEnforcement)],
    // Stopped at the panel door, before the screen is reached.
    'inactive admin' => [fn () => staffUser(UserRole::Admin, isActive: false)],
]);

test('an officer opening an assessment\'s View appears in the access log', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA));

    $row = AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->getKey())->sole();

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $w->officerA->getKey())
        ->assertCanSeeTableRecords([$row])
        ->assertTableColumnStateSet('user.name', $w->officerA->name, $row)
        ->assertTableColumnFormattedStateSet('Action', 'Viewed', $row)
        ->assertTableColumnStateSet('record_type', 'Officer assessment', $row);
});

test('the access log shows no names or phone numbers from the records', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $html = Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView, $f->requestView])
        ->html();

    expect($html)
        // Officer assessment and civilian assessment victims.
        ->not->toContain(STAFF_TEST_PREFIX.'VictimA')
        ->not->toContain(STAFF_TEST_PREFIX.'Victim')
        ->not->toContain('Offender')
        ->not->toContain('(423)555-0100')
        // The resource request's requester.
        ->not->toContain($f->request->FirstName)
        ->not->toContain('(423)555-0177')
        ->not->toContain($f->request->EmailAddress);
});

test('the filters narrow by action, record type and Eastern day', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->filterTable('Action', AssessmentAccess::Download->value)
        ->assertCanSeeTableRecords([$f->adminDownload])
        ->assertCanNotSeeTableRecords([$f->civilianView, $f->requestView]);

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->filterTable('record_type', 'ResourceRequestFormID')
        ->assertCanSeeTableRecords([$f->requestView])
        ->assertCanNotSeeTableRecords([$f->adminDownload, $f->civilianView]);

    $today = now(App\Support\Timezones::DISPLAY)->toDateString();
    $tomorrow = now(App\Support\Timezones::DISPLAY)->addDay()->toDateString();

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->filterTable('accessed_at', ['from' => $today, 'until' => $today])
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView, $f->requestView]);

    Livewire::test(ListAccessLog::class)
        ->filterTable('AccessedBy', $f->admin->getKey())
        ->filterTable('accessed_at', ['from' => $tomorrow])
        ->assertCanNotSeeTableRecords([$f->adminDownload, $f->civilianView, $f->requestView]);
});

test('the access log can only be read: no create, edit or view pages, no actions', function () {
    expect(array_keys(AccessLogResource::getPages()))->toBe(['index'])
        ->and(Route::has('filament.staff.resources.access-log.index'))->toBeTrue()
        ->and(Route::has('filament.staff.resources.access-log.create'))->toBeFalse()
        ->and(Route::has('filament.staff.resources.access-log.edit'))->toBeFalse()
        ->and(Route::has('filament.staff.resources.access-log.view'))->toBeFalse();

    $this->actingAs(staffUser(UserRole::Admin));
    $table = Livewire::test(ListAccessLog::class)->instance()->getTable();

    expect($table->getRecordActions())->toBeEmpty()
        ->and($table->getToolbarActions())->toBeEmpty()
        ->and($table->getHeaderActions())->toBeEmpty()
        ->and(AccessLogResource::canCreate())->toBeFalse();
});
