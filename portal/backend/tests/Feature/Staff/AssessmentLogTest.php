<?php

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use App\Services\AssessmentEditor;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Team plan task 22: one record's views, PDF downloads and edits together, in
time order, in the Log pop-up on Assessment review (App\Filament\Assessments\
ChangeHistory::action(withAccess: true)). Who may see the views is
AssessmentAccessLog::visibleTo() - admins and police admins, never officers -
so an officer's Change log on My Assessments stays edits only. An edit row's
View on the Logs page stays edits only too, and View stays history-free
(AssessmentChangeLogTest).
*/

afterEach(fn () => cleanupStaffData());

// An officer's assessment with a known history, oldest first:
//   Sep 1  the officer views it
//   Sep 2  the officer edits it ("Spelling fix")
//   Sep 3  the police admin downloads its PDF
function pestLoggedAssessment(): object
{
    $agency = pestAgency('Log PD');
    $w = (object) [
        'officer' => pestAgencyMember(UserRole::LawEnforcement, $agency),
        'policeAdmin' => pestAgencyMember(UserRole::PoliceAdmin, $agency),
    ];
    $w->assessment = pestOfficerAssessment($w->officer, 'Logged');

    $at = fn (AssessmentAccessLog $row, string $when) => DB::connection('Portal')->table('assessment_access_log')
        ->where('AccessID', $row->getKey())->update(['AccessedAt' => $when]);

    $at(AssessmentAccessLog::record($w->assessment, AssessmentAccess::View, $w->officer), '2026-09-01 14:00:00');

    $edit = AssessmentEditor::apply($w->assessment, ['VictimLastName' => 'Smyth'], [], 'Spelling fix', $w->officer);
    DB::connection('Portal')->table('assessment_edits')->where('EditID', $edit->getKey())
        ->update(['EditedAt' => '2026-09-02 14:00:00']);

    $at(AssessmentAccessLog::record($w->assessment, AssessmentAccess::Download, $w->policeAdmin), '2026-09-03 14:00:00');

    return $w;
}

// The Log pop-up's content as the button builds it, for checking order.
function pestLogHtml(LawEnforcementAssessment $record, bool $withAccess): string
{
    $action = ChangeHistory::action(withAccess: $withAccess);
    $content = (new ReflectionProperty($action, 'modalContent'))->getValue($action);

    return $content($record->fresh())->render();
}

test('a police admin\'s Log shows views, downloads and edits, newest first, then the submission', function () {
    $w = pestLoggedAssessment();
    $this->actingAs($w->policeAdmin);

    Livewire::test(ListAssessmentReview::class)
        ->assertActionHasLabel(TestAction::make('changeLog')->table($w->assessment), 'Log')
        ->mountAction(TestAction::make('changeLog')->table($w->assessment))
        ->assertMountedActionModalSee(['Viewed', 'Downloaded PDF', 'Spelling fix', 'Submitted']);

    // Opening the Log above was itself a view, so it comes first (Oct, now).
    $html = pestLogHtml($w->assessment, withAccess: true);

    expect($html)->toContain('Every view, PDF download and edit, newest first');

    $order = [
        strpos($html, 'Downloaded PDF'),
        strpos($html, 'Spelling fix'),
        strrpos($html, 'Viewed</span> by '.$w->officer->name),
        strpos($html, 'Submitted</span> by '.$w->officer->name),
    ];

    expect($order)->not->toContain(false)
        ->and($order)->toBe(collect($order)->sort()->values()->all());
});

test('an officer\'s Change log stays edits only - officers never see who opened a record', function () {
    $w = pestLoggedAssessment();
    $this->actingAs($w->officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertActionHasLabel(TestAction::make('changeLog')->table($w->assessment), 'Change log')
        ->mountAction(TestAction::make('changeLog')->table($w->assessment))
        ->assertMountedActionModalSee('Spelling fix')
        ->assertMountedActionModalDontSee(['Downloaded PDF', 'Viewed']);
});

test('the Log only lists the views its viewer may see', function () {
    // An admin may see every view; a police admin of another agency could
    // never reach this record at all (the table is scoped), and an officer is
    // offered no views by AssessmentAccessLog::visibleTo().
    $w = pestLoggedAssessment();

    $this->actingAs($w->officer);
    expect(pestLogHtml($w->assessment, withAccess: true))
        ->not->toContain('Downloaded PDF')
        ->not->toContain('Viewed</span>');

    $this->actingAs(staffUser(UserRole::Admin));
    expect(pestLogHtml($w->assessment, withAccess: true))
        ->toContain('Downloaded PDF')
        ->toContain('Viewed</span>');
});

test('an edit row\'s View on the Logs page stays edits only', function () {
    $w = pestLoggedAssessment();
    $this->actingAs($w->policeAdmin);

    $edit = $w->assessment->edits()->sole();

    Livewire::test(ListLogs::class)
        ->mountAction(TestAction::make('view')->table($edit))
        ->assertMountedActionModalSee('Spelling fix')
        ->assertMountedActionModalDontSee(['Downloaded PDF', 'Viewed']);
});
