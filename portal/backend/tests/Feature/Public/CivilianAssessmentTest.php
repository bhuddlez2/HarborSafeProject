<?php

use App\Support\DateOfBirth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
POST /api/public/civilian-assessments - the public website's lethality
assessment. Unauthenticated, so each test here pins one of the things that keep
it safe to expose: one transaction, server-generated ids, a bare response,
strict input rules, a throttle, and the old per-table write routes closed.

Rows are found and removed by a victim first name only these tests use. The
assessment models refuse Eloquent updates, so cleanup uses the query builder.
*/

const CIVILIAN_TEST_VICTIM = 'PestCivilianVictim';

afterEach(function () {
    $portal = DB::connection('Portal');
    $rows = $portal->table('_private_assessment')
        ->where('VictimFirstName', 'like', CIVILIAN_TEST_VICTIM.'%')
        ->get(['DocumentID', 'AssessmentDocID', 'SubmissionID']);

    cleanupFollowUps('PrivateDocumentID', $rows->pluck('DocumentID'));
    $portal->table('_private_assessment')->where('VictimFirstName', 'like', CIVILIAN_TEST_VICTIM.'%')->delete();
    $portal->table('_assessment_answers')->whereIn('AssessmentDocID', $rows->pluck('AssessmentDocID'))->delete();
    $portal->table('_submitter_info')->whereIn('SubmissionID', $rows->pluck('SubmissionID')->filter())->delete();
});

function civilianPayload(array $overrides = []): array
{
    $answers = [];
    foreach (range(1, 11) as $n) {
        $answers["RiskIndicator{$n}"] = $n % 3 === 0;
    }

    return array_merge([
        'ForWhom' => 'self',
        ...$answers,
        'VictimFirstName' => CIVILIAN_TEST_VICTIM,
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        'VictimDOB' => '1990-04-12',
        'VictimSafePhoneNumber' => '(423) 555-0100',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderDOB' => null,
        'OffenderVictimRelationship' => 'spouse',
    ], $overrides);
}

function civilianStoredRow(string $victimFirstName = CIVILIAN_TEST_VICTIM): ?object
{
    return DB::connection('Portal')->table('_private_assessment')
        ->where('VictimFirstName', $victimFirstName)->first();
}

test('a submission for yourself writes the answers and the record, and no submitter', function () {
    $response = $this->postJson('/api/public/civilian-assessments', civilianPayload());

    $response->assertCreated();

    $row = civilianStoredRow();
    expect($row)->not->toBeNull()
        ->and($row->SubmissionID)->toBeNull()
        ->and($row->VictimSafePhoneNumber)->toBe('(423)555-0100')
        ->and($row->OffenderVictimRelationship)->toBe('spouse');

    $answers = DB::connection('Portal')->table('_assessment_answers')
        ->where('AssessmentDocID', $row->AssessmentDocID)->first();
    expect((bool) $answers->RiskIndicator3)->toBeTrue()
        ->and((bool) $answers->RiskIndicator1)->toBeFalse();
});

test('the response is a bare confirmation: no ids and nothing that was submitted', function () {
    $response = $this->postJson('/api/public/civilian-assessments', civilianPayload());

    $response->assertCreated();
    expect($response->json())->toBe(['message' => 'Assessment received'])
        ->and($response->getContent())->not->toContain(CIVILIAN_TEST_VICTIM)
        ->and($response->getContent())->not->toContain('555-0100');
});

test('someone reporting for another person who gives their name gets a linked submitter row', function () {
    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'ForWhom' => 'other',
        'Anonymous' => false,
        'SubmitterFirstName' => 'Friend',
        'SubmitterLastName' => 'Reporter',
        'SubmitterEmail' => 'friend@example.com',
    ]))->assertCreated();

    $row = civilianStoredRow();
    $submitter = DB::connection('Portal')->table('_submitter_info')
        ->where('SubmissionID', $row->SubmissionID)->first();

    expect($submitter->SubmitterFirstName)->toBe('Friend')
        ->and($submitter->SubmitterEmail)->toBe('friend@example.com');
});

test('an anonymous report for another person writes no submitter row', function () {
    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'ForWhom' => 'other',
        'Anonymous' => true,
    ]))->assertCreated();

    expect(civilianStoredRow()->SubmissionID)->toBeNull();
});

test('contact details are refused on an anonymous or self submission rather than stored', function (array $overrides) {
    $this->postJson('/api/public/civilian-assessments', civilianPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('SubmitterFirstName');

    expect(civilianStoredRow())->toBeNull();
})->with([
    'anonymous' => [['ForWhom' => 'other', 'Anonymous' => true, 'SubmitterFirstName' => 'Friend', 'SubmitterLastName' => 'Reporter']],
    'self' => [['SubmitterFirstName' => 'Friend', 'SubmitterLastName' => 'Reporter']],
]);

test('ids sent by the client are ignored: the server makes its own and links nothing else', function () {
    // Real rows a forged id could try to attach to or overwrite.
    $victimAnswersId = DB::connection('Portal')->table('_assessment_answers')->value('AssessmentDocID');

    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'AssessmentDocID' => $victimAnswersId ?? '11111111-1111-1111-1111-111111111111',
        'SubmissionID' => '22222222-2222-2222-2222-222222222222',
        'DocumentID' => '33333333-3333-3333-3333-333333333333',
        'DateCreated' => '2000-01-01 00:00:00',
    ]))->assertCreated();

    $row = civilianStoredRow();
    expect($row->AssessmentDocID)->not->toBe($victimAnswersId)
        ->and($row->SubmissionID)->toBeNull()
        ->and($row->DocumentID)->not->toBe('33333333-3333-3333-3333-333333333333')
        ->and($row->DateCreated)->not->toStartWith('2000-01-01');
});

test('SQL in a field is stored as plain text and changes nothing else', function () {
    $before = DB::connection('Portal')->table('_private_assessment')->count();
    $sql = "Robert'); DROP TABLE users;--";

    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'VictimFirstName' => CIVILIAN_TEST_VICTIM."'--",
        'OffenderLastName' => $sql,
    ]))->assertCreated();

    expect(civilianStoredRow(CIVILIAN_TEST_VICTIM."'--")->OffenderLastName)->toBe($sql)
        ->and(DB::connection('Portal')->table('_private_assessment')->count())->toBe($before + 1)
        ->and(DB::connection('Portal')->getSchemaBuilder()->hasTable('users'))->toBeTrue();
});

test('bad input is refused and nothing is written', function (array $overrides, string $field) {
    $this->postJson('/api/public/civilian-assessments', civilianPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(civilianStoredRow())->toBeNull();
})->with([
    'missing phone' => [['VictimSafePhoneNumber' => ''], 'VictimSafePhoneNumber'],
    'phone with letters' => [['VictimSafePhoneNumber' => 'call me maybe'], 'VictimSafePhoneNumber'],
    'phone too short' => [['VictimSafePhoneNumber' => '123'], 'VictimSafePhoneNumber'],
    'free-text relationship' => [['OffenderVictimRelationship' => 'Boyfriend'], 'OffenderVictimRelationship'],
    'unknown sex code' => [['VictimSex' => 'X'], 'VictimSex'],
    'missing victim birth date' => [['VictimDOB' => null], 'VictimDOB'],
    'blank victim birth date' => [['VictimDOB' => '   '], 'VictimDOB'],
    'future birth date' => [['VictimDOB' => now()->addDay()->toDateString()], 'VictimDOB'],
    'birth date over 125 years ago' => [fn () => ['OffenderDOB' => Carbon::parse(DateOfBirth::earliest())->subDay()->toDateString()], 'OffenderDOB'],
    'date in another format' => [['OffenderDOB' => '04/12/1990'], 'OffenderDOB'],
    'name over 50 characters' => [['OffenderFirstName' => str_repeat('a', 51)], 'OffenderFirstName'],
    'name as an array' => [['OffenderFirstName' => ['a', 'b']], 'OffenderFirstName'],
    'an answer that is not yes or no' => [['RiskIndicator4' => 'maybe'], 'RiskIndicator4'],
    'a missing answer' => [['RiskIndicator11' => null], 'RiskIndicator11'],
    'who it is for missing' => [['ForWhom' => 'neighbour'], 'ForWhom'],
    'anonymity unanswered for someone else' => [['ForWhom' => 'other'], 'Anonymous'],
    'a named report with no name' => [['ForWhom' => 'other', 'Anonymous' => false], 'SubmitterFirstName'],
    'a bad submitter email' => [['ForWhom' => 'other', 'Anonymous' => false, 'SubmitterFirstName' => 'A', 'SubmitterLastName' => 'B', 'SubmitterEmail' => 'not-an-email'], 'SubmitterEmail'],
]);

test('a birth date exactly 125 years ago, or today, is accepted', function () {
    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'VictimDOB' => DateOfBirth::earliest(),
        'OffenderDOB' => DateOfBirth::latest(),
    ]))->assertCreated();

    expect(civilianStoredRow())->not->toBeNull();
});

test('blank optional fields are stored as null, and names are trimmed', function () {
    $this->postJson('/api/public/civilian-assessments', civilianPayload([
        'VictimLastName' => '  Doe  ',
        'OffenderDOB' => '   ',
    ]))->assertCreated();

    $row = civilianStoredRow();
    expect($row->VictimLastName)->toBe('Doe')
        ->and($row->OffenderDOB)->toBeNull();
});

test('the 11th submission in a minute is throttled', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/public/civilian-assessments', civilianPayload(['VictimFirstName' => CIVILIAN_TEST_VICTIM.$i]))
            ->assertCreated();
    }

    $this->postJson('/api/public/civilian-assessments', civilianPayload(['VictimFirstName' => CIVILIAN_TEST_VICTIM.'11']))
        ->assertStatus(429);

    expect(civilianStoredRow(CIVILIAN_TEST_VICTIM.'11'))->toBeNull();
});

// Removed outright in team plan task 29 (see ApiSurfaceTest); this only
// checks a civilian submission can't reach the tables any other way.
test('the old per-table routes are gone', function (string $url) {
    $this->postJson($url, civilianPayload())->assertNotFound();

    expect(civilianStoredRow())->toBeNull();
})->with(['/api/assessments', '/api/private-assessments', '/api/submitter-info']);

test('the endpoint writes through the restricted connection, in one transaction', function () {
    // Asserted at the source: a refactor that slipped back to the full-access
    // Portal connection would still pass every behavioural test above.
    $source = file_get_contents(app_path('Http/Controllers/PublicCivilianAssessmentController.php'));

    expect($source)->toContain("DB::connection('PortalPublic')->transaction(")
        ->and(substr_count($source, "::on('PortalPublic')->create("))->toBe(3)
        ->and($source)->not->toContain("'Portal')");

    // And no validation rule reads the database (the restricted user can't).
    $rules = file_get_contents(app_path('Http/Requests/Public/CivilianAssessmentStoreRequest.php'));
    expect($rules)->not->toContain("'exists:")->not->toContain("'unique:");
});

test('the restricted database user can insert but cannot read or change anything', function () {
    $public = DB::connection('PortalPublic');

    expect(fn () => $public->table('_private_assessment')->count())->toThrow(QueryException::class)
        ->and(fn () => $public->table('users')->count())->toThrow(QueryException::class)
        ->and(fn () => $public->table('law_enforcement_assessment')->count())->toThrow(QueryException::class)
        ->and(fn () => $public->table('_private_assessment')->where('VictimFirstName', 'nobody')->update(['VictimLastName' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => $public->table('_private_assessment')->where('VictimFirstName', 'nobody')->delete())->toThrow(QueryException::class);
})->skip(
    fn () => config('database.connections.PortalPublic.username') === config('database.connections.Portal.username'),
    'PortalPublic uses the same credentials as Portal here; create the restricted user (Schema_Reference.md) to run this.',
);

test('phone numbers need all 10 digits, for the victim and the submitter alike', function (string $field) {
    $payload = $field === 'SubmitterPhoneNumber'
        ? civilianPayload(['ForWhom' => 'other', 'Anonymous' => false, 'SubmitterFirstName' => 'Sam', 'SubmitterLastName' => 'Reporter', $field => '555-0100'])
        : civilianPayload([$field => '555-0100']);

    $this->postJson('/api/public/civilian-assessments', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => 'area code']);

    expect(civilianStoredRow())->toBeNull();
})->with(['VictimSafePhoneNumber', 'SubmitterPhoneNumber']);
