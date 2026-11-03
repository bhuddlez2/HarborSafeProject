<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
Phone numbers are now stored as (xxx)xxx-xxxx (App\Support\PhoneNumber, set by
each model on save). This brings the numbers already stored into the same
form, so every screen, PDF and export reads alike - and so an officer editing
some other field of an old assessment doesn't log a phone "change" that is
only punctuation.

Punctuation only: the digits never change, and anything that isn't a valid
10-digit number is left exactly as it is, for a person to correct. That is
also why this writes through the query builder rather than AssessmentEditor:
a reformat is not an edit of what the officer recorded, and logging one would
put a change on every assessment.

down() can't restore the old punctuation, which wasn't recorded; it does
nothing.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    private const COLUMNS = [
        ['Portal', 'law_enforcement_assessment', 'DocumentID', 'VictimSafePhoneNumber'],
        ['Portal', '_private_assessment', 'DocumentID', 'VictimSafePhoneNumber'],
        ['Portal', '_submitter_info', 'SubmissionID', 'SubmitterPhoneNumber'],
        ['Feedback', 'resource_request_form', 'FormID', 'SafePhoneNumber'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$connection, $table, $key, $column]) {
            $db = DB::connection($connection);

            $db->table($table)
                ->whereNotNull($column)
                ->select([$key, $column])
                ->orderBy($key)
                ->each(function (object $row) use ($db, $table, $key, $column): void {
                    $formatted = PhoneNumber::format($row->{$column});

                    if ($formatted !== $row->{$column}) {
                        $db->table($table)->where($key, $row->{$key})->update([$column => $formatted]);
                    }
                });
        }
    }

    public function down(): void
    {
        //
    }
};
