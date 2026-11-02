<?php

use App\Support\PersonName;
use App\Support\SensitiveData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
Capitalises every name already saved, word by word, by the rule new names now
follow on save (App\Support\PersonName): only lowercase first letters change,
so a name typed correctly is left alone. Encrypted names are decrypted,
tidied and encrypted again; a staff account's `name` is rebuilt from its parts.

Written through the query builder, so no change log or edit row is recorded:
this is a format clean-up, like 2026_10_19_130000_format_stored_phone_numbers.
Safe to re-run. down() does nothing: the names as first typed aren't kept.
*/
return new class extends Migration
{
    // [connection, table, primary key, name columns], all encrypted
    private const ENCRYPTED = [
        ['Portal', 'law_enforcement_assessment', 'DocumentID', self::PERSON],
        ['Portal', '_private_assessment', 'DocumentID', self::PERSON],
        ['Portal', '_submitter_info', 'SubmissionID', ['SubmitterFirstName', 'SubmitterLastName']],
        ['Feedback', 'resource_request_form', 'FormID', ['FirstName', 'LastName']],
    ];

    private const PERSON = ['OffenderFirstName', 'OffenderLastName', 'VictimFirstName', 'VictimLastName'];

    public function up(): void
    {
        foreach (self::ENCRYPTED as [$connection, $table, $key, $columns]) {
            $this->rewrite($connection, $table, $key, $columns, function (?string $stored): ?string {
                if ($stored === null) {
                    return null;
                }

                $name = SensitiveData::decrypt($stored);
                $tidy = PersonName::capitalize($name);

                return $tidy === $name ? $stored : SensitiveData::encrypt($tidy);
            });
        }

        $this->rewrite('Portal', 'users', 'id', ['first_name', 'last_name'], fn (?string $name): ?string => PersonName::capitalize($name), rebuildName: true);
    }

    public function down(): void
    {
        //
    }

    // Applies $transform to each value, writing back only rows that changed.
    private function rewrite(string $connection, string $table, string $key, array $columns, Closure $transform, bool $rebuildName = false): void
    {
        $db = DB::connection($connection);

        $db->table($table)->select([$key, ...$columns])->orderBy($key)
            ->chunkById(200, function ($rows) use ($db, $table, $key, $columns, $transform, $rebuildName): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} === null ? null : (string) $row->{$column};
                        $new = $transform($value);

                        if ($new !== $value) {
                            $changes[$column] = $new;
                        }
                    }

                    if ($changes === []) {
                        continue;
                    }

                    // users.name is always "First Last" (User::booted()).
                    if ($rebuildName) {
                        $changes['name'] = trim(($changes['first_name'] ?? $row->first_name).' '.($changes['last_name'] ?? $row->last_name));
                    }

                    $db->table($table)->where($key, $row->{$key})->update($changes);
                }
            }, $key);
    }
};
