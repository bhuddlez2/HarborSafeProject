<?php

use App\Enums\AssessmentAccess;
use App\Enums\LogAction;
use App\Enums\UserRole;
use App\Filament\Resources\Logs\LogResource;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\ResourceRequestForm;
use App\Support\Timezones;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
The views and PDF downloads on the Logs page (LogResource, /logs): rows of
assessment_access_log, read-only, naming each record by its kind, submitted
time and id alone - never by anything in it. Officer and civilian assessments
only: resource request views and downloads are recorded in the same table but
never listed (Finishing Touches item 28). Admins see every row; a police
admin with an agency sees only rows about their own agency's officers'
assessments, whoever opened them (AssessmentAccessLog::visibleTo(), the access
half of LogEntry::visibleTo()). Edits mixed in, the filters on these rows and
the page itself: LogsPageTest and ChangeLogFiltersTest.

The "Show" dropdown on the page (ListLogs::$show) picks All, Viewed,
Downloaded PDF or Edited, and the search box matches the person's name. The
shared local database holds real rows too, so tests narrow to their own with
the search box, giving the people they search for unique names.
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

test('an admin opens Logs and sees the access rows, and opening it logs nothing', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $before = AssessmentAccessLog::count();

    $this->get(LogResource::getUrl('index'))->assertOk();

    // Stored UTC, shown Eastern.
    $civilianSubmitted = Carbon::parse($f->civilian->refresh()->DateCreated)
        ->setTimezone(Timezones::DISPLAY)
        ->format('M j, Y g:i a');

    Livewire::test(ListLogs::class)
        ->searchTable(accessLogRename($f->admin, 'Opener'))
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView])
        ->assertCanNotSeeTableRecords([$f->officerView, $f->requestView])
        ->assertTableColumnFormattedStateSet('Action', 'Downloaded PDF', $f->adminDownload)
        ->assertTableColumnStateSet('record', 'Officer assessment', $f->adminDownload)
        ->assertTableColumnStateSet('record', 'Civilian assessment', $f->civilianView)
        ->assertTableColumnHasDescription('record', "Submitted {$civilianSubmitted} · ".substr($f->civilian->DocumentID, 0, 8), $f->civilianView)
        // Access rows have no change count.
        ->assertTableColumnStateSet('changes_count', null, $f->civilianView);

    expect(AssessmentAccessLog::count())->toBe($before);
});

// Finishing Touches item 28: a resource request's PDF downloads and follow-up
// page views are still recorded, but Logs lists assessments only - for every
// role that can open it, whatever Show or the search box say.
test('resource request rows are recorded but never listed on Logs', function (UserRole $role) {
    $f = accessLogFixture();
    $download = AssessmentAccessLog::record($f->request, AssessmentAccess::Download, $f->admin);
    $user = $role === UserRole::Admin ? $f->admin : staffUser($role);
    $this->actingAs($user);

    expect(AssessmentAccessLog::where('ResourceRequestFormID', $f->request->FormID)->count())->toBe(2)
        ->and(LogResource::getEloquentQuery()->whereIn('EntryID', [$f->requestView->AccessID, $download->AccessID])->count())->toBe(0);

    $list = Livewire::test(ListLogs::class)->searchTable(accessLogRename($f->admin, 'Requester'));

    foreach ([ListLogs::SHOW_ALL, LogAction::View->value, LogAction::Download->value] as $show) {
        $list->set('show', $show)->assertCanNotSeeTableRecords([$f->requestView, $download]);
    }

    $list->set('show', ListLogs::SHOW_ALL)->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView]);
})->with([
    'admin' => [UserRole::Admin],
    'super admin' => [UserRole::SuperAdmin],
]);

test('everyone but an active admin or a police admin with an agency is kept out of Logs', function (Closure $makeUser) {
    $user = $makeUser();
    $this->actingAs($user);

    // canAccess() is also what keeps it out of the sidebar; NavigationTest
    // pins every role's sidebar.
    expect(LogResource::canAccess())->toBeFalse();

    if ($user->is_active) {
        $this->get(LogResource::getUrl('index', panel: 'staff'))->assertForbidden();
    }
})->with([
    'secretary' => [fn () => staffUser(UserRole::Secretary)],
    // With an agency they get their agency's rows - see below.
    'police admin with no agency' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, null)],
    'officer' => [fn () => staffUser(UserRole::LawEnforcement)],
    // Stopped at the panel door, before the screen is reached.
    'inactive admin' => [fn () => staffUser(UserRole::Admin, isActive: false)],
]);

test('an officer opening an assessment\'s View appears in Logs', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->officerA);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make('view')->table($w->assessmentA));

    $row = AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->getKey())->sole();

    $this->actingAs(staffUser(UserRole::Admin));
    $name = accessLogRename($w->officerA, 'Viewer');

    Livewire::test(ListLogs::class)
        ->searchTable($name)
        ->assertCanSeeTableRecords([$row])
        ->assertTableColumnStateSet('user.name', $w->officerA->refresh()->name, $row)
        ->assertTableColumnFormattedStateSet('Action', 'Viewed', $row)
        ->assertTableColumnStateSet('record', 'Officer assessment', $row);
});

test('Logs shows no names or phone numbers from the records', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $html = Livewire::test(ListLogs::class)
        ->searchTable(accessLogRename($f->admin, 'Opener'))
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView])
        ->html();

    expect($html)
        // Officer assessment and civilian assessment victims.
        ->not->toContain(STAFF_TEST_PREFIX.'VictimA')
        ->not->toContain(STAFF_TEST_PREFIX.'Victim')
        // The offender's name is "Test Offender"; the word alone is in the
        // filter card's labels ("Offender first name").
        ->not->toMatch('/Offender(?! (first|last) name)/')
        ->not->toContain('(423)555-0100')
        // The resource request's requester (its row isn't listed at all).
        ->not->toContain($f->request->FirstName)
        ->not->toContain('(423)555-0177')
        ->not->toContain($f->request->EmailAddress);
});

test('the Show dropdown picks All, Viewed, Downloaded PDF or Edited, and applies at once', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);
    $name = accessLogRename($f->admin, 'Opener');

    // The admin's own edit, so the search box narrows to it as well.
    $edit = App\Services\AssessmentEditor::apply($f->assessmentA, ['OffenderVictimRelationship' => 'spouse'], [], 'Pest Show', $f->admin);

    $views = [$f->civilianView];
    $downloads = [$f->adminDownload];
    $edits = [$edit];

    // All is the default, and shows every kind.
    Livewire::test(ListLogs::class)
        ->assertSet('show', ListLogs::SHOW_ALL)
        ->searchTable($name)
        ->assertCanSeeTableRecords([...$views, ...$downloads, ...$edits])
        ->assertCanNotSeeTableRecords([$f->requestView])
        // Viewed: views only.
        ->set('show', LogAction::View->value)
        ->assertCanSeeTableRecords($views)
        ->assertCanNotSeeTableRecords([...$downloads, ...$edits])
        // Downloaded PDF: downloads only.
        ->set('show', LogAction::Download->value)
        ->assertCanSeeTableRecords($downloads)
        ->assertCanNotSeeTableRecords([...$views, ...$edits])
        // Edited: edits only.
        ->set('show', LogAction::Edit->value)
        ->assertCanSeeTableRecords($edits)
        ->assertCanNotSeeTableRecords([...$views, ...$downloads])
        // Back to All.
        ->set('show', ListLogs::SHOW_ALL)
        ->assertCanSeeTableRecords([...$views, ...$downloads, ...$edits]);
});

test('the Show options come from LogAction, after All, with the access log\'s own labels', function () {
    expect(ListLogs::showOptions())->toBe([
        'all' => 'All',
        'view' => 'Viewed',
        'download' => 'Downloaded PDF',
        'edit' => 'Edited',
    ]);

    foreach (AssessmentAccess::cases() as $access) {
        expect(LogAction::from($access->value)->label())->toBe($access->label());
    }
});

test('a tampered Show value shows All, still scoped', function () {
    $f = accessLogAgencyFixture();
    $this->actingAs($f->policeAdminA);

    Livewire::test(ListLogs::class)
        ->set('show', 'everything')
        ->assertCanSeeTableRecords([$f->officerView, $f->adminDownload, $f->policeAdminView])
        ->assertCanNotSeeTableRecords([$f->otherAgencyView, $f->civilianView, $f->requestView]);
});

test('Show sits above the table, outside the Filters card', function (string $who) {
    $f = accessLogAgencyFixture();
    $this->actingAs($who === 'admin' ? $f->admin : $f->policeAdminA);

    $table = Livewire::test(ListLogs::class)->instance()->getTable();

    // Show is the page's own dropdown, not a table filter; the card behind
    // the Filters button holds ChangeLogFilters.
    expect(array_keys($table->getFilters()))->not->toContain('show')
        ->and($table->isFilterable())->toBeTrue();

    $page = $this->get(LogResource::getUrl('index'))->assertOk()->getContent();

    expect($page)->toContain('fi-ta-filters')
        ->and(preg_match('/>\s*Show\s*</', $page))->toBe(1);
})->with(['admin', 'police admin']);

test('Logs can only be read: no create, edit or view pages, and no action on access rows', function () {
    expect(array_keys(LogResource::getPages()))->toBe(['index'])
        ->and(Route::has('filament.staff.resources.logs.index'))->toBeTrue()
        ->and(Route::has('filament.staff.resources.logs.create'))->toBeFalse()
        ->and(Route::has('filament.staff.resources.logs.edit'))->toBeFalse()
        ->and(Route::has('filament.staff.resources.logs.view'))->toBeFalse()
        ->and(LogResource::getUrl('index'))->toEndWith('/logs');

    $f = accessLogFixture();
    $this->actingAs($f->admin);
    $list = Livewire::test(ListLogs::class)->searchTable(accessLogRename($f->admin, 'Opener'));
    $table = $list->instance()->getTable();

    expect(collect($table->getRecordActions())->map(fn ($action) => $action->getName())->values()->all())->toBe(['view'])
        ->and($table->getToolbarActions())->toBeEmpty()
        ->and($table->getHeaderActions())->toBeEmpty()
        ->and(LogResource::canCreate())->toBeFalse();

    foreach ([$f->adminDownload, $f->civilianView] as $row) {
        $list->assertActionHidden(TestAction::make('view')->table($row));
    }
});

/*
Police admins: their own agency's officer-assessment rows, whoever opened
them, and nothing else (Filament_CMS_Design.md section 5, rule 5).
*/

// Assessment A opened by its officer, its police admin and an admin;
// assessment B by its officer; a civilian assessment and a resource request
// by the admin (the request's row is never listed).
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

    expect(LogResource::canAccess())->toBeTrue();

    $this->get(LogResource::getUrl('index'))->assertOk();

    $ownAgency = [$f->officerView, $f->adminDownload, $f->policeAdminView];
    $elsewhere = [$f->otherAgencyView, $f->civilianView, $f->requestView];

    Livewire::test(ListLogs::class)
        ->assertCanSeeTableRecords($ownAgency)
        ->assertCanNotSeeTableRecords($elsewhere);

    // Agency A exists only in this test, so the scoped rows are exactly these.
    expect(LogResource::getEloquentQuery()->pluck('EntryID')->sort()->values()->all())
        ->toBe(collect($ownAgency)->pluck('AccessID')->sort()->values()->all());
});

test('a police admin of the other agency sees the other half, and nothing without an agency', function () {
    $f = accessLogAgencyFixture();

    $this->actingAs($f->policeAdminB);

    expect(LogResource::getEloquentQuery()->pluck('EntryID')->all())
        ->toBe([$f->otherAgencyView->AccessID]);

    $noAgency = pestAgencyMember(UserRole::PoliceAdmin, null);
    $this->actingAs($noAgency);

    expect(LogResource::canAccess())->toBeFalse()
        ->and(LogResource::getEloquentQuery()->count())->toBe(0)
        ->and(AssessmentAccessLog::visibleTo($noAgency)->count())->toBe(0);

    $this->get(LogResource::getUrl('index'))->assertForbidden();
});

test('an admin still sees every assessment row, of both kinds', function () {
    $f = accessLogAgencyFixture();
    $this->actingAs($f->admin);

    $all = [$f->officerView, $f->adminDownload, $f->policeAdminView, $f->otherAgencyView, $f->civilianView];

    expect(LogResource::getEloquentQuery()->whereIn('EntryID', collect([...$all, $f->requestView])->pluck('AccessID'))->count())->toBe(5);

    Livewire::test(ListLogs::class)
        ->searchTable(accessLogRename($f->officerB, 'Elsewhere'))
        ->assertCanSeeTableRecords([$f->otherAgencyView]);
});

test('Show, search and the police admin\'s scope together never reach another agency', function () {
    $f = accessLogAgencyFixture();
    $nameB = accessLogRename($f->officerB, 'Elsewhere');
    $this->actingAs($f->policeAdminA);

    foreach (ListLogs::showOptions() as $value => $label) {
        Livewire::test(ListLogs::class)
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
        Livewire::test(ListLogs::class)
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

    Livewire::test(ListLogs::class)
        ->searchTable($nameB)
        ->assertCanNotSeeTableRecords([$f->otherAgencyView])
        ->assertCountTableRecords(0);

    // The search itself works for this police admin.
    Livewire::test(ListLogs::class)
        ->searchTable($nameA)
        ->assertCanSeeTableRecords([$f->officerView])
        ->assertCountTableRecords(1);
});

test('an admin searching still finds rows of both assessment kinds, and no resource request', function () {
    $f = accessLogAgencyFixture();
    $name = accessLogRename($f->admin, 'Ashworth');
    $this->actingAs($f->admin);

    Livewire::test(ListLogs::class)
        ->searchTable(strtolower($name))
        ->assertCanSeeTableRecords([$f->adminDownload, $f->civilianView])
        ->assertCanNotSeeTableRecords([$f->officerView, $f->policeAdminView, $f->otherAgencyView, $f->requestView])
        ->assertCountTableRecords(2);
});

test('only the Who column is searched, never record ids', function () {
    $f = accessLogFixture();
    $this->actingAs($f->admin);

    $list = Livewire::test(ListLogs::class);

    $searchable = collect($list->instance()->getTable()->getColumns())
        ->filter(fn ($column): bool => $column->isSearchable())
        ->keys()
        ->values()
        ->all();

    expect($searchable)->toBe(['user.name']);

    $list->searchTable(substr($f->request->FormID, 0, 8))
        ->assertCanNotSeeTableRecords([$f->requestView, $f->adminDownload, $f->civilianView]);
});
