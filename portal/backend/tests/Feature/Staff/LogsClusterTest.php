<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Clusters\Logs\LogsCluster;
use App\Filament\Clusters\Logs\Resources\AccessLog\AccessLogResource;
use App\Filament\Clusters\Logs\Resources\AccessLog\Pages\ListAccessLog;
use App\Filament\Clusters\Logs\Resources\AssessmentEdits\AssessmentEditResource;
use App\Filament\Clusters\Logs\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Models\AssessmentAccessLog;
use App\Services\AssessmentEditor;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
The Logs page (App\Filament\Clusters\Logs, /logs): one sidebar item with two
tabs across the top - Access log (/logs/access) and Change log
(/logs/changes). Admins and police admins with an agency open both; everyone
else, including a police admin with no agency, is refused and has no Logs
item. The old addresses redirect to the tabs.

What each tab shows is pinned in AccessLogScreenTest, AssessmentChangeLogTest
and ChangeLogFiltersTest; this file covers the page around them.
*/

afterEach(fn () => cleanupStaffData());

test('/logs opens the Access log tab, and both tabs show, in order', function (string $who) {
    $w = pestTwoAgencies();
    $user = $who === 'admin' ? staffUser(UserRole::Admin) : $w->policeAdminA;
    $this->actingAs($user);

    $this->get(LogsCluster::getUrl())
        ->assertRedirect(AccessLogResource::getUrl('index'));

    expect(AccessLogResource::getUrl('index'))->toEndWith('/logs/access')
        ->and(AssessmentEditResource::getUrl('index'))->toEndWith('/logs/changes');

    foreach ([AccessLogResource::getUrl('index'), AssessmentEditResource::getUrl('index')] as $tab) {
        $this->get($tab)
            ->assertOk()
            ->assertSeeInOrder(['Access log', 'Change log'])
            ->assertSee(AccessLogResource::getUrl('index'), false)
            ->assertSee(AssessmentEditResource::getUrl('index'), false);
    }
})->with(['admin', 'police admin']);

test('everyone else is refused Logs and both tabs, and has no Logs item', function (Closure $makeUser) {
    $user = $makeUser();
    $this->actingAs($user);
    Filament::setCurrentPanel('staff');

    expect(LogsCluster::canAccess())->toBeFalse()
        ->and(LogsCluster::shouldRegisterNavigation())->toBeFalse()
        ->and(AccessLogResource::canAccess())->toBeFalse()
        ->and(AssessmentEditResource::canAccess())->toBeFalse();

    foreach ([LogsCluster::getUrl(), AccessLogResource::getUrl('index'), AssessmentEditResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }
})->with([
    'secretary' => [fn () => staffUser(UserRole::Secretary)],
    'officer' => [fn () => staffUser(UserRole::LawEnforcement)],
    'police admin with no agency' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, null)],
    // Stopped at the panel door.
    'inactive admin' => [fn () => staffUser(UserRole::Admin, isActive: false)],
]);

test('each tab keeps a police admin to their own agency', function () {
    $w = pestTwoAgencies();
    $editA = AssessmentEditor::apply($w->assessmentA, ['VictimLastName' => 'Ames'], [], 'A edit', $w->officerA);
    $editB = AssessmentEditor::apply($w->assessmentB, ['VictimLastName' => 'Bell'], [], 'B edit', $w->officerB);
    $viewA = AssessmentAccessLog::record($w->assessmentA, AssessmentAccess::View, $w->officerA);
    $viewB = AssessmentAccessLog::record($w->assessmentB, AssessmentAccess::Download, $w->officerB);

    $this->actingAs($w->policeAdminA);

    Livewire::test(ListAssessmentEdits::class)
        ->assertCanSeeTableRecords([$editA])
        ->assertCanNotSeeTableRecords([$editB]);

    Livewire::test(ListAccessLog::class)
        ->assertCanSeeTableRecords([$viewA])
        ->assertCanNotSeeTableRecords([$viewB]);

    $this->actingAs($w->policeAdminB);

    Livewire::test(ListAssessmentEdits::class)
        ->assertCanSeeTableRecords([$editB])
        ->assertCanNotSeeTableRecords([$editA]);

    Livewire::test(ListAccessLog::class)
        ->assertCanSeeTableRecords([$viewB])
        ->assertCanNotSeeTableRecords([$viewA]);
});

test('the old addresses redirect to the new tabs, keeping sub-path and query', function (string $old, string $new) {
    $this->get($old)->assertRedirect($new);
})->with([
    'access log' => ['/access-log', '/logs/access'],
    'access log with query' => ['/access-log?page=2', '/logs/access?page=2'],
    'access log sub-path' => ['/access-log/anything', '/logs/access/anything'],
    'change log' => ['/assessment-changes', '/logs/changes'],
    'change log with query' => ['/assessment-changes?page=3', '/logs/changes?page=3'],
]);

test('an admin following an old link lands on the tab', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $this->followingRedirects()
        ->get('/assessment-changes')
        ->assertOk()
        ->assertSee('Change log');
});
