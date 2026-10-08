<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
The victim's safe phone number becomes required on both assessment tables:
the organisation needs to be able to follow up with every victim after an
assessment is submitted. The forms and both APIs require it too (NewAssessment
wizard, the officer edit pop-up, LawEnforcementAssessmentController,
PrivateAssessmentController, portal/frontend's civilian flow).

Rows that already lack a number would make NOT NULL fail, so they are dealt
with first - differently depending on where this runs:

  - local / testing: filled with PLACEHOLDER_PHONE. Every such row there is
    seeded test data, and one shared obviously-fake number is fine.
  - anywhere else: the migration stops and lists the records. A made-up number
    in a real victim's record would look like a way to reach them; someone has
    to supply the real ones (through the officer edit, so it is logged) first.

The fill goes through the query builder on purpose: LawEnforcementAssessment
refuses any update outside App\Services\AssessmentEditor, and test data does
not need a change-log entry.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    private const TABLES = ['law_enforcement_assessment', '_private_assessment'];

    private const PLACEHOLDER_PHONE = '8658675309';

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $missing = DB::connection('Portal')->table($table)
                ->where(fn ($query) => $query->whereNull('VictimSafePhoneNumber')->orWhere('VictimSafePhoneNumber', ''));

            if (! $missing->exists()) {
                continue;
            }

            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException(
                    "{$table} has assessments with no victim safe phone number, so it cannot be made required. "
                    .'Add the real numbers first (DocumentID: '.$missing->pluck('DocumentID')->implode(', ').').'
                );
            }

            $missing->update(['VictimSafePhoneNumber' => self::PLACEHOLDER_PHONE]);
        }

        foreach (self::TABLES as $table) {
            Schema::connection('Portal')->table($table, function (Blueprint $table) {
                $table->string('VictimSafePhoneNumber', 20)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        // The filled-in placeholder numbers are not removed: they can't be told
        // apart from a number someone actually entered.
        foreach (self::TABLES as $table) {
            Schema::connection('Portal')->table($table, function (Blueprint $table) {
                $table->string('VictimSafePhoneNumber', 20)->nullable()->change();
            });
        }
    }
};
