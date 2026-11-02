<?php

use App\Support\SensitiveData;
use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Analytics\AssessmentSections;
use App\Filament\Clusters\Analytics\AnalyticsCluster;
use App\Filament\Clusters\Analytics\Pages\CivilianAssessmentAnalytics;
use App\Filament\Clusters\Analytics\Pages\FeedbackAnalytics;
use App\Filament\Clusters\Analytics\Pages\FollowUpAnalytics;
use App\Filament\Clusters\Analytics\Pages\OfficerAssessmentAnalytics;
use App\Models\LawEnforcementAssessment;
use App\Models\SubmitterInfo;
use App\Services\Analytics\AssessmentStats;
use App\Services\Analytics\FeedbackStats;
use App\Services\Analytics\FollowUpStats;
use App\Services\FollowUps;
use App\Support\Analytics\DateRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
Analytics (team plan task 28): the counting, the date range and its buckets,
the filters, and who may open which tab (the matrix itself is in
PanelAccessTest).

The suite runs against the shared local database, so the counting tests put
their forms in March 2003, a month nothing else uses, and count only that
month.
*/

afterEach(function () {
    cleanupStaffData();
    cleanupCivilianAssessments();
    cleanupPublicFormData();
});

function analyticsMarch2003(): DateRange
{
    return DateRange::custom('2003-03-01', '2003-03-31');
}

// Sets one row's timestamp column directly (the assessment models refuse
// update() outside the editor).
function analyticsBackdate(string $connection, string $table, string $key, string $id, string $column, string $utc): void
{
    DB::connection($connection)->table($table)->where($key, $id)->update([$column => $utc]);
}

// An officer assessment in March 2003 with exactly the given questions answered yes.
function analyticsOfficerAssessment($officer, string $victim, array $yes, string $createdUtc, array $details = []): LawEnforcementAssessment
{
    // No birth date unless given, so the age chart's "Not given" is tested
    // (pestOfficerAssessment() fills one in by default).
    $assessment = pestOfficerAssessment($officer, $victim, ['VictimDOB' => null, ...$details]);

    DB::connection('Portal')->table('_assessment_answers')
        ->where('AssessmentDocID', $assessment->AssessmentDocID)
        ->update(collect(range(1, 11))->mapWithKeys(fn (int $i): array => ["RiskIndicator{$i}" => in_array($i, $yes, true)])->all());

    analyticsBackdate('Portal', 'law_enforcement_assessment', 'DocumentID', $assessment->DocumentID, 'DateCreated', $createdUtc);

    return $assessment;
}

// Three in March 2003 (the last at 11:30 pm Eastern on the 31st) and one just
// after it, in one agency.
function analyticsOfficerFixtures(): object
{
    $agency = pestAgency('Analytics PD');
    $officer = pestAgencyMember(UserRole::LawEnforcement, $agency);

    $one = analyticsOfficerAssessment($officer, 'AnalyticsOne', [1], '2003-03-05 17:00:00', [
        'OffenderVictimRelationship' => 'spouse',
        'VictimDOB' => '1980-03-06',
    ]);
    analyticsOfficerAssessment($officer, 'AnalyticsTwo', [4, 5], '2003-03-20 17:00:00', [
        'OffenderSex' => 'F',
        'OffenderVictimRelationship' => 'other',
        'OffenderVictimRelationshipOther' => 'Neighbour',
    ]);
    analyticsOfficerAssessment($officer, 'AnalyticsThree', [4, 5, 6, 7], '2003-04-01 04:30:00', [
        'VictimSex' => 'M',
        'OffenderSex' => 'F',
    ]);
    // 1 am Eastern on April 1: outside the range.
    analyticsOfficerAssessment($officer, 'AnalyticsLate', [1, 2, 3], '2003-04-01 06:00:00');

    DB::connection('Portal')->table('assessment_edits')->insert([
        'EditID' => (string) Str::uuid(),
        'DocumentID' => $one->DocumentID,
        'ChangedBy' => $officer->getKey(),
        'Reason' => 'Pest analytics',
        'EditedAt' => '2003-03-06 12:00:00',
    ]);

    return (object) ['agency' => $agency, 'officer' => $officer];
}

test('officer assessments are counted by Eastern day, with the agreed high-risk rule', function () {
    $fixtures = analyticsOfficerFixtures();

    $stats = app(AssessmentStats::class)->officer(analyticsMarch2003());

    expect($stats['total'])->toBe(3)
        // Q1 alone, and 4 yes without Q1-3; not the one with two yes.
        ->and($stats['highRisk'])->toBe(2)
        ->and($stats['averageYes'])->toBe(2.3)
        ->and($stats['perQuestion'])->toBe([1 => 1, 2 => 0, 3 => 0, 4 => 2, 5 => 2, 6 => 1, 7 => 1, 8 => 0, 9 => 0, 10 => 0, 11 => 0])
        ->and(array_filter($stats['yesSpread']))->toBe([1 => 1, 2 => 1, 4 => 1])
        ->and($stats['edited'])->toBe(1)
        ->and($stats['agencies'][$fixtures->agency->name])->toBe(3)
        ->and($stats['victimSex'])->toBe(['Female' => 2, 'Male' => 1, 'Other' => 0])
        ->and($stats['offenderSex'])->toBe(['Female' => 2, 'Male' => 1, 'Other' => 0])
        ->and($stats['relationships']['Spouse'])->toBe(1)
        ->and($stats['relationships']['Other'])->toBe(1)
        ->and($stats['relationships']['Not given'])->toBe(1)
        // 22 on the day it was submitted (a birthday the day after).
        ->and($stats['victimAge']['18–24'])->toBe(1)
        ->and($stats['victimAge']['Not given'])->toBe(2)
        ->and(array_sum(array_column($stats['periods'], 'value')))->toBe(3)
        ->and(end($stats['periods'])['value'])->toBe(1);
});

test('each filter narrows on its own or with the others', function () {
    // One: victim F, offender M, spouse. Two: F, F, other. Three: M, F, none.
    $fixtures = analyticsOfficerFixtures();
    $elsewhere = pestAgency('Analytics Elsewhere PD');
    analyticsOfficerAssessment(pestAgencyMember(UserRole::LawEnforcement, $elsewhere), 'AnalyticsElsewhere', [2], '2003-03-10 17:00:00', [
        'OffenderVictimRelationship' => 'spouse',
    ]);
    $stats = app(AssessmentStats::class);
    $march = analyticsMarch2003();

    expect($stats->officer($march, 'F')['total'])->toBe(3)
        ->and($stats->officer($march, 'F', 'F')['total'])->toBe(1)
        ->and($stats->officer($march, offenderSex: 'F')['total'])->toBe(2)
        ->and($stats->officer($march, 'M', 'M')['total'])->toBe(0)
        ->and($stats->officer($march, 'M', 'M')['averageYes'])->toBeNull()
        ->and($stats->officer($march, relationship: 'spouse')['total'])->toBe(2)
        ->and($stats->officer($march, relationship: 'other')['total'])->toBe(1)
        ->and($stats->officer($march, 'M', relationship: 'spouse')['total'])->toBe(0)
        // By the submitter's current agency.
        ->and($stats->officer($march, agency: $fixtures->agency->id)['total'])->toBe(3)
        ->and($stats->officer($march, agency: $elsewhere->id)['total'])->toBe(1)
        ->and($stats->officer($march, relationship: 'spouse', agency: $elsewhere->id)['perQuestion'][2])->toBe(1)
        ->and($stats->officer($march, 'F', 'M', 'spouse', $fixtures->agency->id)['total'])->toBe(1);
});

test('a breakdown chart is left out while its own filter is set', function () {
    $stats = app(AssessmentStats::class)->officer(analyticsMarch2003());
    $titles = fn (array $filters): array => array_column(AssessmentSections::build($stats, analyticsMarch2003(), true, $filters)[0]['cards'], 'title');
    $breakdowns = ['By Agency', 'Relationship to Victim', 'Victim Sex', 'Offender Sex'];

    expect(array_intersect($breakdowns, $titles([])))->toHaveCount(4)
        ->and(array_values(array_intersect($breakdowns, $titles(['victimSex' => 'F', 'offenderSex' => null, 'relationship' => null, 'agency' => null]))))
        ->toBe(['By Agency', 'Relationship to Victim', 'Offender Sex'])
        ->and(array_values(array_intersect($breakdowns, $titles(['victimSex' => null, 'offenderSex' => 'M', 'relationship' => 'spouse', 'agency' => 1]))))
        ->toBe(['Victim Sex']);
});

test('civilian assessments count how many left contact details', function () {
    $submitter = SubmitterInfo::create([
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Submitter',
        'SubmitterLastName' => 'Doe',
        'SubmitterEmail' => 'pest-analytics@example.com',
    ]);

    foreach ([pestCivilianAssessment([1, 2]), pestCivilianAssessment([4], $submitter)] as $assessment) {
        analyticsBackdate('Portal', '_private_assessment', 'DocumentID', $assessment->DocumentID, 'DateCreated', '2003-03-12 15:00:00');
    }

    $stats = app(AssessmentStats::class)->civilian(analyticsMarch2003());

    expect($stats['total'])->toBe(2)
        ->and($stats['withContact'])->toBe(1)
        ->and($stats['highRisk'])->toBe(1)
        ->and($stats['relationships']['Spouse'])->toBe(2);
});

test('service feedback and resource requests are counted per service, county and type', function () {
    $feedback = DB::connection('Feedback');
    $service = publicFormsMakeService('Pest Analytics Service');

    foreach ([[5, 'online', '2003-03-03 15:00:00'], [3, 'staff', '2003-03-04 15:00:00'], [1, 'online', '2003-04-04 15:00:00']] as [$rating, $source, $at]) {
        $feedback->table('service_feedback')->insert([
            'FormID' => (string) Str::uuid(), 'ServiceID' => $service, 'Rating' => $rating,
            'Comment' => 'Pest', 'Source' => $source, 'SubmissionDate' => $at,
        ]);
    }

    $county = publicFormsMakeCounty('Pest Analytics County');
    $typeA = publicFormsMakeResource('Pest Analytics Type A');
    $typeB = publicFormsMakeResource('Pest Analytics Type B');
    $withCounty = (string) Str::uuid();

    foreach ([[$withCounty, $county, 'online'], [(string) Str::uuid(), null, 'staff']] as [$id, $countyId, $source]) {
        // Encrypted by hand, as the model would (App\Support\SensitiveData).
        $feedback->table('resource_request_form')->insert([
            'FormID' => $id, 'FirstName' => SensitiveData::encrypt(publicFormsRunToken()), 'LastName' => SensitiveData::encrypt('Requester'),
            'CountyID' => $countyId, 'Source' => $source, 'SubmissionDate' => '2003-03-08 15:00:00',
        ]);
    }

    $feedback->table('resource_request_resource_types')->insert([
        ['FormID' => $withCounty, 'ResourceTypeID' => $typeA],
        ['FormID' => $withCounty, 'ResourceTypeID' => $typeB],
    ]);

    $stats = app(FeedbackStats::class);
    $forms = $stats->serviceFeedback(analyticsMarch2003());
    $requests = $stats->resourceRequests(analyticsMarch2003());

    expect($forms['total'])->toBe(2)
        ->and($forms['staff'])->toBe(1)
        ->and($forms['averageRating'])->toBe(4.0)
        ->and($forms['perService']['Pest Analytics Service'])->toBe(2)
        ->and($forms['averagePerService']['Pest Analytics Service'])->toBe(4.0)
        ->and($forms['ratings'])->toBe([1 => 0, 2 => 0, 3 => 1, 4 => 0, 5 => 1])
        ->and($requests['total'])->toBe(2)
        ->and($requests['staff'])->toBe(1)
        ->and($requests['perCounty']['Pest Analytics County'])->toBe(1)
        ->and($requests['perCounty']['Not given'])->toBe(1)
        ->and($requests['countiesReached'])->toBe(1)
        ->and($requests['perType']['Pest Analytics Type A'])->toBe(1)
        ->and($requests['perType']['Pest Analytics Type B'])->toBe(1);
});

test('follow-ups count statuses and the time to first contact', function () {
    $admin = staffUser(UserRole::Admin);
    $reached = pestFollowUp(FollowUpKind::LawEnforcementAssessment);
    $waiting = pestFollowUp(FollowUpKind::CivilianAssessment);

    foreach ([$reached, $waiting] as $followUp) {
        analyticsBackdate('Portal', 'follow_ups', 'FollowUpID', $followUp->FollowUpID, 'SubmittedAt', '2003-03-10 10:00:00');
    }

    $followUps = app(FollowUps::class);
    $first = $followUps->update($reached->refresh(), FollowUpStatus::AttemptedContact, 'Left a message.', $admin);
    analyticsBackdate('Portal', 'follow_up_entries', 'EntryID', $first->EntryID, 'EnteredAt', '2003-03-10 15:00:00');
    $later = $followUps->update($reached->refresh(), FollowUpStatus::Reached, 'Spoke to the victim.', $admin);
    analyticsBackdate('Portal', 'follow_up_entries', 'EntryID', $later->EntryID, 'EnteredAt', '2003-03-12 15:00:00');

    $stats = app(FollowUpStats::class)->summary(analyticsMarch2003());

    expect($stats['total'])->toBe(2)
        ->and($stats['reached'])->toBe(1)
        ->and($stats['open'])->toBe(1)
        ->and($stats['byKind']['law_enforcement_assessment']['reached'])->toBe(1)
        ->and($stats['byKind']['civilian_assessment']['needs_follow_up'])->toBe(1)
        // From the first status change, not the latest.
        ->and($stats['medianMinutes'])->toBe(300)
        ->and($stats['contactBands']['4–24 h'])->toBe(1)
        ->and(FollowUpStats::formatMinutes(300))->toBe('5 hours')
        ->and(FollowUpStats::formatMinutes(45))->toBe('45 minutes')
        ->and(FollowUpStats::formatMinutes(5 * 1440))->toBe('5 days');
});

test('presets and their chart buckets', function () {
    $days30 = DateRange::preset('30d', '2026-10-21');
    $months3 = DateRange::preset('3m', '2026-10-21');
    $months12 = DateRange::preset('12m', '2026-10-21');

    expect([$days30->fromDate, $days30->toDate])->toBe(['2026-09-22', '2026-10-21'])
        // Weeks counted back from the last day: only the oldest is short.
        ->and(array_column($days30->buckets(), 'label'))->toBe(['Sep 22–23', 'Sep 24–30', 'Oct 1–7', 'Oct 8–14', 'Oct 15–21'])
        ->and($months3->fromDate)->toBe('2026-08-01')
        ->and(array_column($months3->buckets(), 'label'))->toBe(['Aug', 'Sep', 'Oct'])
        ->and($months12->fromDate)->toBe('2025-11-01')
        ->and(count($months12->buckets()))->toBe(12)
        ->and($months12->buckets()[0]['label'])->toBe("Nov '25")
        ->and(DateRange::custom('2020-01-01', '2023-06-30')->unit())->toBe('year')
        ->and(array_column(DateRange::custom('2020-01-01', '2023-06-30')->buckets(), 'label'))->toBe(['2020', '2021', '2022', '2023']);

    // Midnight Eastern in UTC, either side of the March clock change.
    $spring = collect(DateRange::custom('2026-02-01', '2026-04-30')->buckets());
    expect($spring->pluck('start')->all())->toBe(['2026-02-01 05:00:00', '2026-03-01 05:00:00', '2026-04-01 04:00:00'])
        ->and($spring->last()['end'])->toBe('2026-05-01 03:59:59');
});

// One user per test: the panel's AuthenticateSession signs out a session
// whose user changes part way through.
test('every tab opens for an admin', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    foreach ([OfficerAssessmentAnalytics::class, CivilianAssessmentAnalytics::class, FeedbackAnalytics::class, FollowUpAnalytics::class] as $page) {
        $this->get($page::getUrl())->assertOk()->assertSee('Analytics');
    }

    $this->get(AnalyticsCluster::getUrl())->assertRedirect(OfficerAssessmentAnalytics::getUrl());
});

test('a secretary gets only the feedback tab', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $this->get(AnalyticsCluster::getUrl())->assertRedirect(FeedbackAnalytics::getUrl());
    $this->get(FeedbackAnalytics::getUrl())->assertOk()->assertDontSee('Law-Enforcement Assessments');
    $this->get(OfficerAssessmentAnalytics::getUrl())->assertForbidden();
    $this->get(CivilianAssessmentAnalytics::getUrl())->assertForbidden();
    $this->get(FollowUpAnalytics::getUrl())->assertForbidden();
});

test('a police admin gets no Analytics at all', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Analytics PD')));

    $this->get(AnalyticsCluster::getUrl())->assertForbidden();
    $this->get(OfficerAssessmentAnalytics::getUrl())->assertForbidden();
});

test('the page shows totals, never names or phone numbers', function () {
    analyticsOfficerFixtures();
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(OfficerAssessmentAnalytics::class)
        ->call('setRange', 'custom')
        ->set('customFrom', '2003-03-01')
        ->set('customTo', '2003-03-31')
        ->call('applyCustomRange')
        ->assertHasNoErrors()
        ->assertSee('Mar 1 – Mar 31, 2003')
        ->assertSee(AssessmentStats::HIGH_RISK_RULE)
        ->assertSee('2 (67%)')
        ->assertDontSee('AnalyticsOne')
        ->assertDontSee('Neighbour')
        ->assertDontSee('(423)555-0100');
});

test('the custom range needs a start on or before its end', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(FeedbackAnalytics::class)
        ->call('setRange', 'custom')
        ->set('customFrom', '2003-03-31')
        ->set('customTo', '2003-03-01')
        ->call('applyCustomRange')
        ->assertHasErrors(['customTo' => 'after_or_equal']);
});

test('every filter is optional, carries across tabs and can be reset', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $agency = pestAgency('Analytics Filter PD');

    Livewire::test(OfficerAssessmentAnalytics::class)
        ->assertSeeHtml('aria-controls="analytics-filters"')
        ->assertSeeHtml('id="analytics-agency"')
        ->set('draftRelationship', 'spouse')
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertSet('relationship', 'spouse')
        ->assertSet('victimSex', null)
        // Only what is set is listed.
        ->assertSee('Relationship: Spouse')
        ->assertDontSee('Victim Sex:')
        ->set('draftVictimSex', 'F')
        ->set('draftAgency', (string) $agency->id)
        ->call('applyFilters')
        ->assertSet('agency', $agency->id)
        ->assertSee('Victim Sex: Female · Relationship: Spouse · Agency: '.$agency->name);

    // Civilian assessments have no agency: no box, not in the summary, but
    // still remembered for the Law-Enforcement tab.
    Livewire::test(CivilianAssessmentAnalytics::class)
        ->assertSet('relationship', 'spouse')
        ->assertDontSeeHtml('id="analytics-agency"')
        ->assertDontSee($agency->name)
        ->set('draftOffenderSex', 'M')
        ->call('applyFilters')
        ->assertSee('Victim Sex: Female · Offender Sex: Male · Relationship: Spouse');

    Livewire::test(OfficerAssessmentAnalytics::class)
        ->assertSet('agency', $agency->id)
        ->assertSet('offenderSex', 'M')
        ->call('resetFilters')
        ->assertSet('victimSex', null)
        ->assertSet('offenderSex', null)
        ->assertSet('relationship', null)
        ->assertSet('agency', null)
        ->assertDontSee('Victim Sex: Female');

    // Feedback has nothing to filter by.
    Livewire::test(FeedbackAnalytics::class)->assertDontSeeHtml('aria-controls="analytics-filters"');
});

test('a filter value not on its list is refused', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(OfficerAssessmentAnalytics::class)
        ->set('draftVictimSex', 'X')
        ->set('draftRelationship', 'neighbour')
        ->set('draftAgency', '999999999')
        ->call('applyFilters')
        ->assertHasErrors(['draftVictimSex' => 'in', 'draftRelationship' => 'in', 'draftAgency' => 'in'])
        ->assertSet('victimSex', null)
        ->assertSet('relationship', null)
        ->assertSet('agency', null);
});

test('the applied filter can only change through the page', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    expect(fn () => Livewire::test(OfficerAssessmentAnalytics::class)->set('victimSex', 'F'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
