<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
assessment_access_log now also records resource requests (team plan task 16,
part 5): opening a request's follow-up page and downloading its PDF both show
the requester's safe phone number. ResourceRequestFormID points at
resource_request_form in the Feedback database, so it has no foreign key.

The CHECK becomes "exactly one of the three". The table keeps its name: it
is the one place staff access to a safe phone number is recorded.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->table('assessment_access_log', function (Blueprint $table) {
            $table->uuid('ResourceRequestFormID')->nullable()->after('PrivateDocumentID');
            $table->index(['ResourceRequestFormID', 'AccessedAt']);
        });

        $portal = DB::connection('Portal');
        $portal->statement('ALTER TABLE assessment_access_log DROP CONSTRAINT assessment_access_log_one_assessment');
        $portal->statement(
            'ALTER TABLE assessment_access_log ADD CONSTRAINT assessment_access_log_one_form CHECK (
                (LawEnforcementDocumentID IS NOT NULL) + (PrivateDocumentID IS NOT NULL) + (ResourceRequestFormID IS NOT NULL) = 1
            )',
        );
    }

    public function down(): void
    {
        $portal = DB::connection('Portal');
        $portal->table('assessment_access_log')->whereNotNull('ResourceRequestFormID')->delete();
        $portal->statement('ALTER TABLE assessment_access_log DROP CONSTRAINT assessment_access_log_one_form');
        $portal->statement(
            'ALTER TABLE assessment_access_log ADD CONSTRAINT assessment_access_log_one_assessment
             CHECK ((LawEnforcementDocumentID IS NULL) <> (PrivateDocumentID IS NULL))',
        );

        Schema::connection('Portal')->table('assessment_access_log', function (Blueprint $table) {
            $table->dropIndex(['ResourceRequestFormID', 'AccessedAt']);
            $table->dropColumn('ResourceRequestFormID');
        });
    }
};
