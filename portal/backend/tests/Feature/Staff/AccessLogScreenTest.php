<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Clusters\Logs\Resources\AccessLog\AccessLogResource;
use App\Filament\Clusters\Logs\Resources\AccessLog\Pages\ListAccessLog;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\ResourceRequestForm;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
The Access log tab of Logs (AccessLogResource, /logs/access): rows of
assessment_access_log, read-only, naming each record by its submitted time and
id alone - never by anything in it. Admins see every row; a police admin with
an agency sees only rows about their own agency's officers' assessments,
whoever opened them (AssessmentAccessLog::visibleTo()).

No table filters: one "Show" dropdown on the page (ListAccessLog::$show) picks
All, Viewed or Downloaded PDF, and the search box matches the opener's name.
The shared local database holds real rows too, so tests narrow to their own
with the search box, giving the people they search for unique names.
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
        ->searchTable(accessLogRename($f->admin, 'Opener'))
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
    // With an agency they get their agency's rows - see below.
    'police admin with no agency' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, null)],
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
    $name = accessLogRename($w->officerA, 'Viewer');

    Livewire::test(ListAccessLog::class)
        ->searchTable($name)
        ->assertCanSeeTableRecords([$row])
        ->assertTableColumnStateSet('user.name', $w->officerA->refresh()->name, $row)
        ->assertTableColumnFormattedStateSet('Action', 'Viewed', $row)
        ->assertTableColumnStateSet('record_type', 'Officer assessment', $row);
});

test('the access log shows no names or phone numbers from the records', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $html = Livewire::test(ListAccessLog::class)
        ->searchTable(accessLogRename($f->admin, 'Opener'))
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

test('the Show dropdown picks All, Viewed or Downloaded PDF, and applies at once', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);
    $name = accessLogRename($f->admin, 'Opener');

    $views = [$f->civilianView, $f->requestView];
    $downloads = [$f->adminDownload];

    // All is the default, and shows both kinds.
    Livewire::test(ListAccessLog::class)
        ->assertSet('show', ListAccessLog::SHOW_ALL)
        ->searchTable($name)
        ->assertCanSeeTableRecords([...$views, ...$downloads])
        // Viewed: views only.
        ->set('show', AssessmentAccess::View->value)
        ->assertCanSeeTableRecords($views)
        ->assertCanNotSeeTableRecords($downloads)
        // Downloaded PDF: downloads only.
        ->set('show', AssessmentAccess::Download->value)
        ->assertCanSeeTableRecords($downloads)
        ->assertCanNotSeeTableRecords($views)
        // Back to All.
        ->set('show', ListAccessLog::SHOW_ALL)
        ->assertCanSeeTableRecords([...$views, ...$downloads]);
});

test('the Show options come from AssessmentAccess, after All', function () {
    expect(ListAccessLog::showOptions())->toBe([
        'all' => 'All',
        'view' => 'Viewed',
        'download' => 'Downloaded PDF',
    ]);
});

test('a tampered Show value shows All, still scoped', function () {
    $f = accessLogAgencyFixture();
    $this->actingAs($f->policeAdminA);

    Livewire::test(ListAccessLog::class)
        ->set('show', 'everything')
        ->assertCanSeeTableRecords([$f->officerView, $f->adminDownload, $f->policeAdminView])
        ->assertCanNotSeeTableRecords([$f->otherAgencyView, $f->civilianView, $f->requestView]);
});

test('the access log has no table filters, so no Filters button', function (string $who) {
    $f = accessLogAgencyFixture();
    $this->actingAs($who === 'admin' ? $f->admin : $f->policeAdminA);

    $table = Livewire::test(ListAccessLog::class)->instance()->getTable();

    expect($table->getFilters())->toBeEmpty()
        ->and($table->isFilterable())->toBeFalse();

    // The page draws the Show dropdown and no Filament filter markup at all
    // (no Filters button, menu or card). The Change log tab keeps its card.
    $page = $this->get(AccessLogResource::getUrl('index'))->assertOk()->getContent();

    expect($page)->not->toContain('fi-ta-filters')
        ->and(preg_match('/>\s*Show\s*</', $page))->toBe(1)
        ->and($this->get(App\Filament\Clusters\Logs\Resources\AssessmentEdits\AssessmentEditResource::getUrl('index'))->getContent())
        ->toContain('fi-ta-filters');
})->with(['admin', 'police admin']);

test('the access log can only be read: no create, edit or view pages, no actions', function () {
    expect(array_keys(AccessLogResource::getPages()))->toBe(['index'])
        ->and(Route::has('filament.staff.logs.resources.access.index'))->toBeTrue()
        ->and(Route::has('filament.staff.logs.resources.access.create'))->toBeFalse()
        ->and(Route::has('filament.staff.logs.resources.access.edit'))->toBeFalse()
        ->and(Route::has('filament.staff.logs.resources.access.view'))->toBeFalse()
        ->and(AccessLogResource::getUrl('index'))->toEndWith('/logs/access');

    $this->actingAs(staffUser(UserRole::Admin));
    $table = Livewire::test(ListAccessLog::class)->instance()->getTable();

    expect($table->getRecordActions())->toBeEmpty()
        ->and($table->getToolbarActions())->toBeEmpty()
        ->and($table->getHeaderActions())->toBeEmpty()
        ->and(AccessLogResource::canCreate())->toBeFalse();
});

/*
Police admins: their own agency's officer-assessment rows, whoever opened
them, and nothing else (Filament_CMS_Design.md section 5, rule 5).
*/

// Assessment A opened by its officer, its police admin and an admin;
// assessment B by its officer; a civilian assessment and a resource request
// by the admin.
function accessLogAgencyFixture(): object
{
    $f = accessLogFixture();

    return (object) [
        ...(array) $f,
        'policeAdminView' => AssessmentAccessLog::record($f->assessmentA, AssessmentAccess::View, $f->policeAdminA),
        'otherAgencyView' => AssessmentAccessLog::record($f->assessmentB, AssessmentAccess::View, $f->officerB),
    ];
}

test('a police admin sees only their agency\'s officer-assessment rows, whoever opened them', function () {
    $f = accessLogAgencyFixture();
    $this->actingAs($f->policeAdminA);

    expect(AccessLogResource::canAccess())->toBeTrue();

    $this->get(AccessLogResource::getUrl('index'))->assertOk();

    $ownAgency = [$f->officerView, $f->adminDownload, $f->policeAdminView];
    $elsewhere = [$f->otherAgencyView, $f->civilianView, $f->requestView];

    Livewire::test(ListAccessLog::class)
        ->assertCanSeeTableRecords($ownAgency)
        ->assertCanNotSeeTableRecords($elsewhere);

    // Agency A exists only in this test, so the scoped rows are exactly these.
    expect(AccessLogResource::getEloquentQuery()->pluck('AccessID')->sort()->values()->all())
        ->toBe(collect($ownAgency)->pluck('AccessID')->sort()->values()->all());
});

test('a police admin of the other agency sees the other half, and nothing without an agency', function () {
    $f = accessLogAgencyFixture();

    $this->actingAs($f->policeAdminB);

    expect(AccessLogResource::getEloquentQuery()->pluck('AccessID')->all())
        ->toBe([$f->otherAgencyView->AccessID]);

    $noAgency = pestAgencyMember(UserRole::PoliceAdmin, null);
    $this->actingAs($noAgency);

    expect(AccessLogResource::canAccess())->toBeFalse()
        ->and(AccessLogResource::getEloquentQuery()->count())->toBe(0)
        ->and(AssessmentAccessLog::visibleTo($noAgency)->count())->toBe(0);

    $this->get(AccessLogResource::getUrl('index'))->assertForbidden();
});

test('an admin still sees every row, of every kind', function () {
    $f = accessLogAgencyFixture();
    $this->actingAs($f->admin);

    $all = [$f->officerView, $f->adminDownload, $f->policeAdminView, $f->otherAgencyView, $f->civilianView, $f->requestView];

    expect(AccessLogResource::getEloquentQuery()->whereIn('AccessID', collect($all)->pluck('AccessID'))->count())->toBe(6);

    Livewire::test(ListAccessLog::class)
        ->searchTable(accessLogRename($f->officerB, 'Elsewhere'))
        ->assertCanSeeTableRecords([$f->otherAgencyView]);
});

test('Show, search and the police admin\'s scope together never reach another agency', function () {
    $f = accessLogAgencyFixture();
    $nameB = accessLogRename($f->officerB, 'Elsewhere');
    $this->actingAs($f->policeAdminA);

    foreach (ListAccessLog::showOptions() as $value => $label) {
        Livewire::test(ListAccessLog::class)
            ->set('show', $value)
            ->assertCanNotSeeTableRecords([$f->otherAgencyView, $f->civilianView, $f->requestView])
            ->searchTable($nameB)
            ->assertCountTableRecords(0);
    }
});

/*
The search box: it searches only the Who column (the opener's name, partial,
case-insensitive), and only within the rows visibleTo() already allows.
*/

// The fixture's officers share the name "Pest law_enforcement", so each
// search test gives the people it searches for a name of their own. The
// random part keeps real rows in the shared local database from matching.
function accessLogRename(App\Models\User $user, string $lastName): string
{
    $user->forceFill(['last_name' => $lastName.Illuminate\Support\Str::random(6)])->save();

    return $user->refresh()->last_name;
}

test('searching part of a name, in any case, shows only that person\'s rows', function () {
    $f = accessLogAgencyFixture();
    $name = accessLogRename($f->officerA, 'Smithfield');
    $this->actingAs($f->admin);

    $others = [$f->adminDownload, $f->policeAdminView, $f->otherAgencyView, $f->civilianView, $f->requestView];

    foreach ([strtolower(substr($name, 2)), strtoupper(substr($name, 0, -2))] as $search) {
        Livewire::test(ListAccessLog::class)
            ->searchTable($search)
            ->assertCanSeeTableRecords([$f->officerView])
            ->assertCanNotSeeTableRecords($others)
            ->assertCountTableRecords(1);
    }
});

test('a police admin searching for another agency\'s officer gets no rows', function () {
    $f = accessLogAgencyFixture();
    $nameA = accessLogRename($f->officerA, 'Marlowe');
    $nameB = accessLogRename($f->officerB, 'Okonkwo');
    $this->actingAs($f->policeAdminA);

    Livewire::test(ListAccessLog::class)
        ->searchTable($nameB)
        ->assertCanNotSeeTableRecords([$f->otherAgencyView])
        ->assertCountTableRecords(0);

    // The search itself works for this police admin.
    Livewire::test(ListAccessLog::class)
        ->searchTable($nameA)
        ->assertCanSeeTableRecords([$f->officerView])
        ->assertCountTableRecords(1);
});

test('an admin searching still finds rows of all three record types', function () {
    $f = accessLogAgencyFixture();
    $name = accessLogRename($f->admin, 'Ashworth');
    $this->actingAs($f->admin);

    Livewire::test(ListAccessLog::class)
        ->searchTable(strtolower($name))
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView, $f->requestView])
        ->assertCanNotSeeTableRecords([$f->officerView, $f->policeAdminView, $f->otherAgencyView])
        ->assertCountTableRecords(3);
});

test('only the Who column is searched, never record ids', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $list = Livewire::test(ListAccessLog::class);

    $searchable = collect($list->instance()->getTable()->getColumns())
        ->filter(fn ($column): bool => $column->isSearchable())
        ->keys()
        ->values()
        ->all();

    expect($searchable)->toBe(['user.name']);

    $list->searchTable(substr($f->request->FormID, 0, 8))
        ->assertCanNotSeeTableRecords([$f->requestView, $f->adminDownload, $f->civilianView]);
});
