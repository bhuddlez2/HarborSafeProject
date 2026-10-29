<?php

use App\Enums\FollowUpKind;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentChangeLog;
use App\Models\FollowUpEntry;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Models\SubmitterInfo;
use App\Services\AssessmentEditor;
use App\Services\FollowUps;
use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Finishing Touches item 14: sensitive columns are encrypted before they reach
the database, under DATA_ENCRYPTION_KEY and never APP_KEY, and every screen
that searched or counted them still works (App\Support\SensitiveData).
*/

beforeEach(fn () => $this->dataKey = config('app.data_key'));

afterEach(function () {
    // Some tests swap the key; cleanup has to read rows with the real one.
    config(['app.data_key' => $this->dataKey, 'app.previous_data_keys' => []]);
    Model::encryptUsing(SensitiveData::encrypter());

    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// Each column must hold ciphertext that opens with the data key, gives back
// $expected, and does not open with APP_KEY.
function expectEncryptedColumns(string $connection, string $table, string $key, string $id, array $expected): void
{
    $raw = (array) DB::connection($connection)->table($table)->where($key, $id)->first(array_keys($expected));
    $appKey = Encrypter::supported(base64_decode(substr((string) config('app.key'), 7)), config('app.cipher'))
        ? new Encrypter(base64_decode(substr((string) config('app.key'), 7)), config('app.cipher'))
        : null;

    foreach ($expected as $column => $value) {
        expect($raw[$column])->not->toBe($value, "{$table}.{$column} is stored in the clear")
            ->and(SensitiveData::decrypt($raw[$column]))->toBe($value, "{$table}.{$column} doesn't decrypt to its value");

        if ($appKey !== null) {
            expect(fn () => $appKey->decrypt($raw[$column], false))
                ->toThrow(Illuminate\Contracts\Encryption\DecryptException::class);
        }
    }
}

test('every detail of an officer assessment is encrypted, and the answers are not', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Cipher PD'));
    $assessment = pestOfficerAssessment($officer, 'Cipher', [
        'OffenderDOB' => '1985-06-30',
        'OffenderVictimRelationship' => 'other',
        'OffenderVictimRelationshipOther' => 'Neighbour',
    ]);

    expectEncryptedColumns('Portal', 'law_enforcement_assessment', 'DocumentID', $assessment->getKey(), [
        'VictimFirstName' => STAFF_TEST_PREFIX.'Cipher',
        'VictimLastName' => 'Doe',
        'VictimSex' => 'F',
        'VictimDOB' => '1990-01-01',
        'VictimSafePhoneNumber' => '(423)555-0100',
        'OffenderFirstName' => 'Test',
        'OffenderLastName' => 'Offender',
        'OffenderSex' => 'M',
        'OffenderDOB' => '1985-06-30',
        'OffenderVictimRelationship' => 'other',
        'OffenderVictimRelationshipOther' => 'Neighbour',
    ]);

    // Read back as plain values, with the phone still formatted.
    $fresh = LawEnforcementAssessment::find($assessment->getKey());
    expect($fresh->VictimSafePhoneNumber)->toBe('(423)555-0100')
        ->and($fresh->OffenderVictimRelationshipOther)->toBe('Neighbour');

    $answers = DB::connection('Portal')->table('_assessment_answers')->where('AssessmentDocID', $assessment->AssessmentDocID)->first();
    expect((int) $answers->RiskIndicator1)->toBe(1)
        ->and((int) $answers->RiskIndicator2)->toBe(0);
});

test('a civilian submission and its submitter are encrypted', function () {
    $submitter = SubmitterInfo::create([
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Friend',
        'SubmitterLastName' => 'Reporter',
        'SubmitterEmail' => 'friend@example.com',
        'SubmitterPhoneNumber' => '423 555 0199',
    ]);
    $civilian = pestCivilianAssessment(submitter: $submitter);

    expectEncryptedColumns('Portal', '_private_assessment', 'DocumentID', $civilian->getKey(), [
        'VictimFirstName' => STAFF_TEST_PREFIX.'Victim',
        'VictimSafePhoneNumber' => '(423)555-0100',
        'OffenderVictimRelationship' => 'spouse',
    ]);
    expectEncryptedColumns('Portal', '_submitter_info', 'SubmissionID', $submitter->getKey(), [
        'SubmitterFirstName' => STAFF_TEST_PREFIX.'Friend',
        'SubmitterLastName' => 'Reporter',
        'SubmitterEmail' => 'friend@example.com',
        'SubmitterPhoneNumber' => '(423)555-0199',
    ]);
});

test('a resource request from the website stores its requester and message encrypted', function () {
    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => 'asking@example.com',
        'SafePhoneNumber' => '423.555.0142',
        'Message' => 'Please only call after 6.',
    ])->assertCreated();

    expectEncryptedColumns('Feedback', 'resource_request_form', 'FormID', $response->json('FormID'), [
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => 'asking@example.com',
        'SafePhoneNumber' => '(423)555-0142',
        'Message' => 'Please only call after 6.',
    ]);
});

test('an edit logs the plain old and new values, encrypted, and an unchanged field logs nothing', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Edit Cipher PD'));
    $assessment = pestOfficerAssessment($officer, 'Edit');

    $edit = AssessmentEditor::apply($assessment, [
        'VictimLastName' => 'Smith',
        'VictimFirstName' => STAFF_TEST_PREFIX.'Edit',     // unchanged
        'VictimSafePhoneNumber' => '423-555-0100',         // same number, other punctuation
    ], [], 'Spelling', $officer);

    $rows = AssessmentChangeLog::where('EditID', $edit->getKey())->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->ChangeField)->toBe('VictimLastName')
        ->and($rows->first()->PreviousValue)->toBe('Doe')
        ->and($rows->first()->NewValue)->toBe('Smith');

    expectEncryptedColumns('Portal', 'assessment_change_log', 'ChangeLogID', $rows->first()->getKey(), [
        'PreviousValue' => 'Doe',
        'NewValue' => 'Smith',
    ]);

    // A save that changes nothing writes nothing.
    expect(AssessmentEditor::apply($assessment->fresh(), ['VictimLastName' => 'Smith'], [], 'Again', $officer))->toBeNull();
});

test('a follow-up note is encrypted', function () {
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);
    $entry = app(FollowUps::class)->update($followUp, null, 'Spoke with her, moving Friday.', staffUser(UserRole::Admin));

    expect(FollowUpEntry::find($entry->getKey())->Note)->toBe('Spoke with her, moving Friday.');
    expectEncryptedColumns('Portal', 'follow_up_entries', 'EntryID', $entry->getKey(), [
        'Note' => 'Spoke with her, moving Friday.',
    ]);
});

// The assessment tables have no search bar: their name filters search the
// encrypted names (AssessmentFilters::encryptedTextContains()). Resource
// requests keep a search bar over the requester's name and email.
test('name filters find victims and offenders, and search finds requesters, by part of their name', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Search PD'));
    $mine = pestOfficerAssessment($officer, 'Quintessa', ['OffenderLastName' => 'Brightwater']);
    $other = pestOfficerAssessment($officer, 'Leopold');

    $this->actingAs($officer);
    pestApplyFilter(Livewire::test(ListLawEnforcementAssessments::class), 'victim_first_name', 'quintes')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
    pestApplyFilter(Livewire::test(ListLawEnforcementAssessments::class), 'offender_last_name', 'BRIGHTWAT')
        ->assertCanSeeTableRecords([$mine])
        ->assertCountTableRecords(1);

    $this->actingAs(staffUser(UserRole::Admin));
    pestApplyFilter(Livewire::test(ListAssessmentReview::class), 'victim_first_name', STAFF_TEST_PREFIX.'Leop')
        ->assertCanSeeTableRecords([$other])
        ->assertCountTableRecords(1);

    $civilian = pestCivilianAssessment(overrides: ['VictimFirstName' => STAFF_TEST_PREFIX.'Ottoline']);
    pestApplyFilter(Livewire::test(ListCivilianAssessments::class), 'victim_first_name', 'ottoline')
        ->assertCanSeeTableRecords([$civilian])
        ->assertCountTableRecords(1);

    $request = ResourceRequestForm::create([
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Wolstenholme',
        'EmailAddress' => 'wolsten@example.com',
    ]);
    Livewire::test(ListResourceRequests::class)
        ->searchTable('wolstenh')
        ->assertCanSeeTableRecords([$request])
        ->assertCountTableRecords(1)
        ->searchTable('wolsten@example')
        ->assertCanSeeTableRecords([$request])
        ->assertCountTableRecords(1);
});

test('a name filter never reaches rows the viewer may not see', function () {
    $w = pestTwoAgencies();
    $this->actingAs($w->policeAdminA);

    $nameB = LawEnforcementAssessment::find($w->assessmentB->getKey())->VictimFirstName;

    pestApplyFilter(Livewire::test(ListAssessmentReview::class), 'victim_first_name', $nameB)
        ->assertCountTableRecords(0);
});

test('the migration encrypts exactly the columns the models encrypt', function () {
    $migration = require database_path('migrations/2026_10_27_120000_encrypt_sensitive_columns.php');
    $columns = (new ReflectionClassConstant($migration, 'COLUMNS'))->getValue();

    $models = [
        'law_enforcement_assessment' => LawEnforcementAssessment::class,
        '_private_assessment' => PrivateAssessment::class,
        '_submitter_info' => SubmitterInfo::class,
        'assessment_change_log' => AssessmentChangeLog::class,
        'follow_up_entries' => FollowUpEntry::class,
        'resource_request_form' => ResourceRequestForm::class,
    ];

    expect(array_column($columns, 1))->toEqualCanonicalizing(array_keys($models));

    foreach ($columns as [$connection, $table, , $list]) {
        $model = new $models[$table];

        expect($model->getConnectionName())->toBe($connection)
            ->and(array_keys($list))->toEqualCanonicalizing($model::ENCRYPTED);

        foreach ($model::ENCRYPTED as $column) {
            expect($model->getCasts()[$column] ?? null)->toBe('encrypted', "{$table}.{$column} isn't cast as encrypted");
        }
    }
});

test('without DATA_ENCRYPTION_KEY nothing sensitive is read or written', function () {
    config(['app.data_key' => null]);
    Model::encryptUsing(SensitiveData::encrypter());

    expect(fn () => new ResourceRequestForm(['FirstName' => 'Nobody']))
        ->toThrow(RuntimeException::class, 'DATA_ENCRYPTION_KEY is not set');

    $before = DB::connection('Feedback')->table('resource_request_form')->count();

    $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => 'nobody@example.com',
    ])->assertServerError();

    expect(DB::connection('Feedback')->table('resource_request_form')->count())->toBe($before);
});

test('records encrypted with a previous key still open after the key changes', function () {
    $request = ResourceRequestForm::create(['FirstName' => publicFormsRunToken(), 'LastName' => 'Rotated', 'EmailAddress' => 'rotated@example.com']);
    $oldKey = config('app.data_key');

    config(['app.data_key' => SensitiveData::generateKey(), 'app.previous_data_keys' => [$oldKey]]);
    Model::encryptUsing(SensitiveData::encrypter());

    expect(ResourceRequestForm::find($request->getKey())->LastName)->toBe('Rotated');

    // Without the old key the record can't be read at all.
    config(['app.previous_data_keys' => []]);
    Model::encryptUsing(SensitiveData::encrypter());

    expect(fn () => ResourceRequestForm::find($request->getKey())->LastName)
        ->toThrow(Illuminate\Contracts\Encryption\DecryptException::class);
});

test('data-key:generate never replaces a key that is set', function () {
    $this->artisan('data-key:generate')
        ->expectsOutputToContain('already set')
        ->assertFailed();

    $this->artisan('data-key:generate --show')
        ->expectsOutputToContain('base64:')
        ->assertSuccessful();
});
