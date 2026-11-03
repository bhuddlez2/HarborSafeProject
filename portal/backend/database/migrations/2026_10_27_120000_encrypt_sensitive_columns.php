<?php

use App\Support\SensitiveData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
Encrypts the sensitive columns (Finishing Touches item 14; the full list and
the reasons are in App\Support\SensitiveData).

Each column becomes TEXT (ciphertext is several times longer than the value,
and a DATE can't hold it), keeping whether it may be empty, and every value
already stored is encrypted in place with DATA_ENCRYPTION_KEY. Set that key
before migrating: without it this stops before changing anything.

Safe to re-run after a failure part-way (MariaDB can't roll back a column
change): a value that already decrypts is left alone. down() decrypts every
value and restores the old column types.

The list is frozen here on purpose: the models' ENCRYPTED constants may grow,
and a later column needs its own migration.
*/
return new class extends Migration
{
    // [connection, table, primary key, [column => [old type, old length, nullable]]]
    private const COLUMNS = [
        ['Portal', 'law_enforcement_assessment', 'DocumentID', self::PERSON],
        ['Portal', '_private_assessment', 'DocumentID', self::PERSON],
        ['Portal', '_submitter_info', 'SubmissionID', [
            'SubmitterEmail' => ['string', 100, true],
            'SubmitterPhoneNumber' => ['string', 20, true],
            'SubmitterFirstName' => ['string', 50, true],
            'SubmitterLastName' => ['string', 50, true],
        ]],
        ['Portal', 'assessment_change_log', 'ChangeLogID', [
            'PreviousValue' => ['string', 50, true],
            'NewValue' => ['string', 50, true],
        ]],
        ['Portal', 'follow_up_entries', 'EntryID', [
            'Note' => ['text', null, false],
        ]],
        ['Feedback', 'resource_request_form', 'FormID', [
            'FirstName' => ['string', 50, false],
            'LastName' => ['string', 50, true],
            'EmailAddress' => ['string', 250, true],
            'SafePhoneNumber' => ['string', 20, true],
            'Message' => ['string', 1000, true],
        ]],
    ];

    private const PERSON = [
        'OffenderFirstName' => ['string', 50, false],
        'OffenderLastName' => ['string', 50, false],
        'OffenderSex' => ['string', 10, false],
        'OffenderDOB' => ['date', null, true],
        'OffenderVictimRelationship' => ['string', 50, true],
        'OffenderVictimRelationshipOther' => ['string', 50, true],
        'VictimFirstName' => ['string', 50, false],
        'VictimLastName' => ['string', 50, false],
        'VictimSex' => ['string', 10, false],
        'VictimDOB' => ['date', null, true],
        'VictimSafePhoneNumber' => ['string', 20, false],
    ];

    public function up(): void
    {
        // Throws with instructions when DATA_ENCRYPTION_KEY is missing, before
        // any column is touched.
        SensitiveData::encrypter()->getKey();

        foreach (self::COLUMNS as [$connection, $table, $key, $columns]) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column => [, , $nullable]) {
                    $blueprint->text($column)->nullable($nullable)->change();
                }
            });

            $this->rewrite($connection, $table, $key, array_keys($columns), function (?string $value): ?string {
                return $value === null || SensitiveData::isEncrypted($value) ? $value : SensitiveData::encrypt($value);
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$connection, $table, $key, $columns]) {
            $this->rewrite($connection, $table, $key, array_keys($columns), function (?string $value): ?string {
                return SensitiveData::isEncrypted($value) ? SensitiveData::decrypt($value) : $value;
            });

            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column => [$type, $length, $nullable]) {
                    $definition = match ($type) {
                        'string' => $blueprint->string($column, $length),
                        'date' => $blueprint->date($column),
                        'text' => $blueprint->text($column),
                    };

                    $definition->nullable($nullable)->change();
                }
            });
        }
    }

    // Applies $transform to each value, writing back only what changed.
    private function rewrite(string $connection, string $table, string $key, array $columns, Closure $transform): void
    {
        $db = DB::connection($connection);

        $db->table($table)->select([$key, ...$columns])->orderBy($key)
            ->chunkById(200, function ($rows) use ($db, $table, $key, $columns, $transform): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} === null ? null : (string) $row->{$column};
                        $new = $transform($value);

                        if ($new !== $value) {
                            $changes[$column] = $new;
                        }
                    }

                    if ($changes !== []) {
                        $db->table($table)->where($key, $row->{$key})->update($changes);
                    }
                }
            }, $key);
    }
};
