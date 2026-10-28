<?php

use App\Enums\AssessmentAccess;
use App\Enums\LogAction;
use App\Enums\UserRole;
use App\Filament\Resources\Logs\LogResource;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Models\AssessmentAccessLog;
use App\Models\AssessmentEdit;
use App\Models\LogEntry;
use App\Models\ResourceRequestForm;
use App\Models\User;
use App\Services\AssessmentEditor;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
The Logs page (App\Filament\Resources\Logs\LogResource, /logs): one list of
views, PDF downloads and edits, newest first, built by LogEntry::visibleTo()
from assessment_access_log and assessment_edits, each scoped before the
union. Officer and civilian assessments only: resource request rows are
recorded in the access log but never listed (Finishing Touches item 28).
Admins and super admins see every row; a police admin with an agency
only their own agency's officer-assessment rows; everyone else, including a
police admin with no agency, is refused and has no Logs item. The old
addresses - its two former tabs and the pages before them - redirect here.

The access rows on their own are pinned in AccessLogScreenTest, the edit rows
and their filters in AssessmentChangeLogTest and ChangeLogFiltersTest; this
file covers the page and the two kinds together.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// Moves a log row's time. Query builder, because both log models are
// append-only.
function logsAt(Model $row, string $when): Model
{
    [$table, $key, $column] = $row instanceof AssessmentEdit
        ? ['assessment_edits', 'EditID', 'EditedAt']
        : ['assessment_access_log', 'AccessID', 'AccessedAt'];

    DB::connection('Portal')->table($table)->where($key, $row->getKey())->update([$column => $when]);

    return $row;
}

// A unique last name, so the search box narrows the shared local database to
// this test's rows.
function logsRename(User $user, string $lastName): string
{
    $user->forceFill(['last_name' => $lastName.Str::random(6)])->save();

    return $user->refresh()->last_name;
}

function logsResourceRequest(): ResourceRequestForm
{
    $request = (new ResourceRequestForm)->forceFill([
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => publicFormsEmail(),
        'SafePhoneNumber' => '423-555-0177',
        'CountyID' => publicFormsMakeCounty(),
        'Message' => 'Pest logs page request',
        'SubmissionDate' => '2026-09-15 18:30:00',
    ]);
    $request->save();

    return $request;
}

// Two agencies. Officer A views, downloads and edits assessment A at three
// different times; officer B edits assessment B; an admin views a civilian
// assessment and a resource request, and downloads assessment A. The resource
// request's view is recorded but never listed.
function logsWorld(): object
{
    $w = pestTwoAgencies();
    $w->admin = staffUser(UserRole::Admin);

    $w->viewA = logsAt(AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::View, $w->officerA), '2026-05-01 12:00:00');
    $w->editA = logsAt(AssessmentEditor::apply($w->assessmentA, ['OffenderVictimRelationship' => 'spouse'], [], 'Pest logs', $w->officerA), '2026-05-02 12:00:00');
    $w->downloadA = logsAt(AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::Download, $w->officerA), '2026-05-03 12:00:00');

    $w->editB = logsAt(AssessmentEditor::apply($w->assessmentB, ['OffenderVictimRelationship' => 'spouse'], [], 'Pest logs', $w->officerB), '2026-05-02 12:00:00');

    $w->civilianView = logsAt(AssessmentAccessLog::record(pestCivilianAssessment(), AssessmentAccess::View, $w->admin), '2026-05-04 12:00:00');
    $w->requestView = logsAt(AssessmentAccessLog::record(logsResourceRequest(), AssessmentAccess::View, $w->admin), '2026-05-05 12:00:00');
    $w->adminDownload = logsAt(AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::Download, $w->admin), '2026-05-06 12:00:00');

    $w->agencyA = [$w->viewA, $w->editA, $w->downloadA, $w->adminDownload];
    $w->notAgencyA = [$w->editB, $w->civilianView];

    return $w;
}

// --- The page ----------------------------------------------------------

test('/logs is one list with no tabs, for an admin and a police admin with an agency', function (string $who) {
    $w = pestTwoAgencies();
    $this->actingAs($who === 'admin' ? staffUser(UserRole::Admin) : $w->policeAdminA);

    expect(LogResource::getUrl('index'))->toEndWith('/logs');

    $this->get(LogResource::getUrl('index'))
        ->assertOk()
        ->assertDontSee('/logs/access', false)
        ->assertDontSee('/logs/changes', false)
        ->assertDontSee('Access log')
        ->assertDontSee('Change log');
})->with(['admin', 'police admin']);

test('a super admin sees every row, like an admin', function () {
    $w = logsWorld();
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    expect(LogResource::canAccess())->toBeTrue()
        ->and(LogResource::getEloquentQuery()->whereIn('EntryID', collect([...$w->agencyA, ...$w->notAgencyA, $w->requestView])->map->getKey())->count())->toBe(6);
});

test('everyone else is refused Logs, and has no Logs item', function (Closure $makeUser) {
    $this->actingAs($makeUser());
    Filament::setCurrentPanel('staff');

    // canAccess() is also what keeps it out of the sidebar; NavigationTest
    // pins every role's sidebar.
    expect(LogResource::canAccess())->toBeFalse();

    $this->get(LogResource::getUrl('index'))->assertForbidden();
})->with([
    'secretary' => [fn () => staffUser(UserRole::Secretary)],
    'officer' => [fn () => staffUser(UserRole::LawEnforcement)],
    'police admin with no agency' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, null)],
    // Stopped at the panel door.
    'inactive admin' => [fn () => staffUser(UserRole::Admin, isActive: false)],
]);

test('opening the page logs nothing', function () {
    $w = logsWorld();
    $this->actingAs($w->admin);
    $before = AssessmentAccessLog::count();

    $this->get(LogResource::getUrl('index'))->assertOk();
    Livewire::test(ListLogs::class);

    expect(AssessmentAccessLog::count())->toBe($before);
});

// --- One list ----------------------------------------------------------

test('views, downloads and edits are one list, newest first, each with its action', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);
    $name = logsRename($w->officerA, 'Merged');

    Livewire::test(ListLogs::class)
        ->searchTable($name)
        ->assertCanSeeTableRecords([$w->downloadA, $w->editA, $w->viewA], inOrder: true)
        ->assertCountTableRecords(3)
        ->assertTableColumnFormattedStateSet('Action', 'Downloaded PDF', $w->downloadA)
        ->assertTableColumnFormattedStateSet('Action', 'Edited', $w->editA)
        ->assertTableColumnFormattedStateSet('Action', 'Viewed', $w->viewA)
        // An edit is about its officer assessment, like the access rows.
        ->assertTableColumnStateSet('record', 'Officer Assessment', $w->editA)
        ->assertTableColumnHasDescription('record', 'Submitted '.Illuminate\Support\Carbon::parse($w->assessmentA->DateCreated)->setTimezone('America/New_York')->format('M j, Y g:i a').' · '.substr($w->assessmentA->getKey(), 0, 8), $w->editA)
        ->assertTableColumnFormattedStateSet('changes_count', '1 field', $w->editA)
        ->assertTableColumnStateSet('changes_count', null, $w->viewA);
});

test('the When column shows the row\'s own time, in Eastern', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);

    // 12:00 UTC is 8:00 am Eastern in May.
    Livewire::test(ListLogs::class)
        ->assertTableColumnFormattedStateSet('OccurredAt', 'May 2, 2026 8:00:00 am', $w->editA)
        ->assertTableColumnFormattedStateSet('OccurredAt', 'May 1, 2026 8:00:00 am', $w->viewA);
});

test('Show narrows the merged list to one kind', function (string $show, array $expected) {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);

    $rows = ['view' => [$w->viewA], 'download' => [$w->downloadA, $w->adminDownload], 'edit' => [$w->editA]];
    $shown = collect($expected)->flatMap(fn (string $kind): array => $rows[$kind])->all();
    $hidden = collect($rows)->except($expected)->flatten()->all();

    $list = Livewire::test(ListLogs::class)
        ->set('show', $show)
        ->assertCanSeeTableRecords($shown)
        ->assertCountTableRecords(count($shown));

    if ($hidden !== []) {
        $list->assertCanNotSeeTableRecords($hidden);
    }
})->with([
    'All' => [ListLogs::SHOW_ALL, ['view', 'download', 'edit']],
    'Viewed' => [LogAction::View->value, ['view']],
    'Downloaded PDF' => [LogAction::Download->value, ['download']],
    'Edited' => [LogAction::Edit->value, ['edit']],
]);

test('search finds views, downloads and edits by the person\'s name, in any case', function () {
    $w = logsWorld();
    $this->actingAs($w->admin);
    $name = logsRename($w->officerA, 'Seeker');

    Livewire::test(ListLogs::class)
        ->searchTable(strtoupper(substr($name, 1)))
        ->assertCanSeeTableRecords([$w->viewA, $w->editA, $w->downloadA])
        ->assertCountTableRecords(3);
});

// --- Who sees what -----------------------------------------------------

test('a police admin sees only their agency\'s rows, never civilian assessments', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);

    Livewire::test(ListLogs::class)
        ->assertCanSeeTableRecords($w->agencyA)
        ->assertCanNotSeeTableRecords([...$w->notAgencyA, $w->requestView]);

    // Agency A exists only in this test, so the scoped rows are exactly these.
    expect(LogResource::getEloquentQuery()->pluck('EntryID')->sort()->values()->all())
        ->toBe(collect($w->agencyA)->map->getKey()->sort()->values()->all());
});

test('the other agency\'s police admin sees none of agency A\'s rows, in the list or by search', function () {
    $w = logsWorld();
    $nameA = logsRename($w->officerA, 'Hidden');
    $this->actingAs($w->policeAdminB);

    expect(LogResource::getEloquentQuery()->pluck('EntryID')->all())->toBe([$w->editB->getKey()]);

    Livewire::test(ListLogs::class)
        ->assertCanSeeTableRecords([$w->editB])
        ->assertCanNotSeeTableRecords([...$w->agencyA, $w->civilianView, $w->requestView])
        ->searchTable($nameA)
        ->assertCountTableRecords(0);
});

test('an admin sees every assessment row of both kinds, and no resource request', function () {
    $w = logsWorld();
    $this->actingAs($w->admin);

    expect(LogResource::getEloquentQuery()->whereIn('EntryID', collect([...$w->agencyA, ...$w->notAgencyA, $w->requestView])->map->getKey())->count())->toBe(6);
});

// --- Filters across the merged list ------------------------------------

test('Who and Date apply to every row, access and edit alike', function () {
    $w = logsWorld();
    $this->actingAs($w->admin);

    pestApplyFilter(Livewire::test(ListLogs::class), 'who', $w->officerA->getKey())
        ->assertCanSeeTableRecords([$w->viewA, $w->editA, $w->downloadA])
        ->assertCanNotSeeTableRecords([$w->adminDownload, $w->editB, $w->civilianView, $w->requestView])
        ->assertCountTableRecords(3)
        ->resetTableFilters();

    // May 4th and 5th, Eastern, as the admin: the civilian view only (the
    // resource request view on the 5th is never listed).
    Livewire::test(ListLogs::class)
        ->set('tableDeferredFilters.who.value', $w->admin->getKey())
        ->set('tableDeferredFilters.date.from', '2026-05-04')
        ->set('tableDeferredFilters.date.until', '2026-05-05')
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->civilianView])
        ->assertCanNotSeeTableRecords([$w->requestView])
        ->assertCountTableRecords(1);
});

test('officer-assessment filters leave only officer-assessment rows', function (string $filter, Closure $value) {
    $w = logsWorld();
    $this->actingAs($w->admin);
    $name = logsRename($w->admin, 'Narrow');

    // The admin's own listed rows: one of the two is not about an officer
    // assessment.
    $list = Livewire::test(ListLogs::class)
        ->searchTable($name)
        ->assertCanSeeTableRecords([$w->adminDownload, $w->civilianView]);

    pestApplyFilter($list, $filter, $value($w))
        ->assertCanSeeTableRecords([$w->adminDownload])
        ->assertCanNotSeeTableRecords([$w->civilianView, $w->requestView])
        ->assertCountTableRecords(1);
})->with([
    'original submitter' => ['submitted_by', fn (object $w) => $w->officerA->getKey()],
    'agency' => ['agency', fn (object $w) => $w->officerA->agencyId()],
    'assessment ID' => ['assessment_id', fn (object $w) => substr($w->assessmentA->getKey(), 0, 8)],
    'victim first name' => ['victim_first_name', fn (object $w) => $w->assessmentA->VictimFirstName],
]);

test('the agency filter is for admins and super admins only', function (string $who, bool $visible) {
    $w = pestTwoAgencies();
    $this->actingAs(match ($who) {
        'admin' => staffUser(UserRole::Admin),
        'super admin' => staffUser(UserRole::SuperAdmin),
        'police admin' => $w->policeAdminA,
    });

    $list = Livewire::test(ListLogs::class);

    $visible ? $list->assertTableFilterVisible('agency') : $list->assertTableFilterHidden('agency');
})->with([
    'admin' => ['admin', true],
    'super admin' => ['super admin', true],
    'police admin' => ['police admin', false],
]);

test('a police admin is offered only their own agency\'s people in every dropdown', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);
    $list = Livewire::test(ListLogs::class);

    foreach (['who', 'submitted_by'] as $filter) {
        $field = pestFilterSelect($list, $filter);

        foreach ([array_keys($field->getOptions()), array_keys($field->getSearchResults('Pest'))] as $options) {
            expect($options)
                ->toContain($w->officerA->getKey())
                ->not->toContain($w->officerB->getKey())
                ->not->toContain($w->policeAdminB->getKey());
        }
    }

    // The admin downloaded agency A's assessment, so they are a Who for
    // agency A's police admin - but never offered as having opened anything else.
    expect(array_keys(pestFilterSelect($list, 'who')->getOptions()))->toContain($w->admin->getKey());

    // A forced value from another agency shows nothing.
    Livewire::test(ListLogs::class)
        ->set('tableFilters.who.value', $w->officerB->getKey())
        ->assertCountTableRecords(0);
});

// --- Edit rows' View ---------------------------------------------------

test('an edit row\'s View shows its changes and logs one view; an access row has none', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);
    $before = AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->getKey())->count();

    Livewire::test(ListLogs::class)
        ->mountAction(TestAction::make('view')->table($w->editA))
        ->assertMountedActionModalSee('Pest logs');

    Livewire::test(ListLogs::class)
        ->assertActionHidden(TestAction::make('view')->table($w->viewA))
        ->assertActionVisible(TestAction::make('view')->table($w->editA));

    expect(AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->getKey())->count())->toBe($before + 1)
        ->and(LogResource::canView(LogEntry::visibleTo($w->policeAdminA)->find($w->viewA->getKey())))->toBeFalse()
        ->and(LogResource::canView(LogEntry::visibleTo($w->policeAdminA)->find($w->editA->getKey())))->toBeTrue();
});

test('a police admin can\'t View another agency\'s edit', function () {
    $w = logsWorld();
    $this->actingAs($w->policeAdminA);

    expect(LogResource::canView(LogEntry::visibleTo(staffUser(UserRole::Admin))->find($w->editB->getKey())))->toBeFalse();
});

// --- The model ---------------------------------------------------------

test('LogEntry is read-only, and has no unscoped way in', function () {
    expect(fn () => LogEntry::query()->count())->toThrow(QueryException::class)
        ->and(fn () => (new LogEntry)->forceFill(['EntryID' => (string) Str::uuid()])->save())->toThrow(LogicException::class);
});

// --- Old addresses -----------------------------------------------------

test('the old addresses redirect to /logs, keeping the query', function (string $old, string $new) {
    $this->get($old)->assertRedirect($new);
})->with([
    'access log tab' => ['/logs/access', '/logs'],
    'access log tab sub-path' => ['/logs/access/anything', '/logs'],
    'change log tab' => ['/logs/changes', '/logs'],
    'change log tab with query' => ['/logs/changes?page=2', '/logs?page=2'],
    'old access log' => ['/access-log', '/logs'],
    'old access log sub-path and query' => ['/access-log/anything?page=2', '/logs?page=2'],
    'old change log' => ['/assessment-changes', '/logs'],
    'old change log sub-path' => ['/assessment-changes/x/y', '/logs'],
]);

test('an admin following an old link lands on Logs', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $this->followingRedirects()
        ->get('/assessment-changes')
        ->assertOk()
        ->assertSee('Logs');
});
