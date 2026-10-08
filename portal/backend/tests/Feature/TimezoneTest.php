<?php

use App\Enums\UserRole;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\ServiceFeedback;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Store UTC, show Eastern (App\Support\Timezones).

These exist because the database used to run in each developer's own time
zone: MariaDB's NOW() - behind every useCurrent() column - followed the
machine, while Laravel's now() is UTC, so the same moment was stored
differently depending on whose machine wrote it.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

test('every database connection runs in UTC and agrees with the app clock', function (string $connection) {
    $session = DB::connection($connection)->selectOne('select @@session.time_zone as tz, NOW() as now');

    expect($session->tz)->toBe('+00:00')
        ->and(abs(Carbon::parse($session->now)->diffInSeconds(now())))->toBeLessThanOrEqual(5);
})->with(['Portal', 'Feedback', 'FeedbackPublic', 'Content', 'ContentPublic']);

test('a time stamped by the database is UTC, like one stamped by Laravel', function () {
    // SubmissionDate on online feedback comes from the column's useCurrent().
    $formId = $this->postJson('/api/public/service-feedback', [
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 5,
    ])->assertCreated()->json('FormID');

    $stamped = ServiceFeedback::find($formId)->SubmissionDate;

    expect(abs($stamped->diffInSeconds(now())))->toBeLessThanOrEqual(5);
});

// 03:00 UTC on 10 March 2026 is 11:00 pm on 9 March in Eastern (EDT began on
// the 8th): the same moment falls on different days in the two zones.
function lateNightAssessment(): object
{
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Clock PD'));
    $assessment = pestOfficerAssessment($officer, 'Late');

    DB::connection('Portal')->table('law_enforcement_assessment')
        ->where('DocumentID', $assessment->getKey())
        ->update(['DateCreated' => '2026-03-10 03:00:00']);

    return (object) ['officer' => $officer, 'assessment' => $assessment->fresh()];
}

test('tables show stored UTC times in Eastern', function () {
    $w = lateNightAssessment();
    $this->actingAs($w->officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->assertTableColumnFormattedStateSet('DateCreated', 'Mar 9, 2026 11:00 pm', $w->assessment);
});

test('date filters count days in Eastern, not UTC', function () {
    $w = lateNightAssessment();
    $this->actingAs($w->officer);

    // Submitted on the 9th as far as anyone in the office is concerned.
    Livewire::test(ListLawEnforcementAssessments::class)
        ->set('tableDeferredFilters.date_submitted.until', '2026-03-09')
        ->call('applyTableFilters')
        ->assertCanSeeTableRecords([$w->assessment]);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->set('tableDeferredFilters.date_submitted.from', '2026-03-10')
        ->call('applyTableFilters')
        ->assertCanNotSeeTableRecords([$w->assessment]);
});
