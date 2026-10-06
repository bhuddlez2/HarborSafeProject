<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
One row per save of a law-enforcement assessment: who, when and why. The
field-level rows in assessment_change_log and assessment_answer_change_log
hang off it through EditID, so one edit that touches four fields reads as one
entry with four changes rather than four unrelated rows.

Written only by App\Services\AssessmentEditor. EditID is nullable on the two
log tables purely so this migration cannot fail on a database that already
has log rows; the editor always sets it.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('assessment_edits', function (Blueprint $table) {
            $table->uuid('EditID')->primary();
            $table->uuid('DocumentID');
            $table->foreign('DocumentID')->references('DocumentID')->on('law_enforcement_assessment');
            $table->foreignId('ChangedBy')->constrained('users');
            $table->string('Reason', 255);
            $table->timestamp('EditedAt')->useCurrent();

            $table->index(['DocumentID', 'EditedAt']);
        });

        Schema::connection('Portal')->table('assessment_change_log', function (Blueprint $table) {
            $table->uuid('EditID')->nullable()->after('ChangeLogID');
            $table->foreign('EditID')->references('EditID')->on('assessment_edits');
        });

        Schema::connection('Portal')->table('assessment_answer_change_log', function (Blueprint $table) {
            $table->uuid('EditID')->nullable()->after('LogID');
            $table->foreign('EditID')->references('EditID')->on('assessment_edits');
        });
    }

    public function down(): void
    {
        foreach (['assessment_change_log', 'assessment_answer_change_log'] as $log) {
            Schema::connection('Portal')->table($log, function (Blueprint $table) {
                $table->dropForeign(['EditID']);
                $table->dropColumn('EditID');
            });
        }

        Schema::connection('Portal')->dropIfExists('assessment_edits');
    }
};
