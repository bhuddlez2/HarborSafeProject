<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
Who looked at or downloaded which assessment, and when - a trail for the
victim's safe phone number, which both the View pop-up and the PDF show.

One table for both kinds of assessment. Each row points at exactly one of
them through its own real foreign key (LawEnforcementDocumentID or
PrivateDocumentID), enforced by a CHECK, so rows can't point at nothing and
the agency scope can join through to law_enforcement_assessment.

Append-only (App\Models\AssessmentAccessLog). The foreign keys restrict
deletion on purpose: an assessment that has been viewed can't be removed
without its trail going too, and nothing in the app deletes assessments.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('assessment_access_log', function (Blueprint $table) {
            $table->uuid('AccessID')->primary();

            $table->uuid('LawEnforcementDocumentID')->nullable();
            $table->foreign('LawEnforcementDocumentID')->references('DocumentID')->on('law_enforcement_assessment');

            $table->uuid('PrivateDocumentID')->nullable();
            $table->foreign('PrivateDocumentID')->references('DocumentID')->on('_private_assessment');

            // App\Enums\AssessmentAccess: view, download.
            $table->string('Action', 20);
            $table->foreignId('AccessedBy')->constrained('users');
            $table->timestamp('AccessedAt')->useCurrent();

            $table->index(['LawEnforcementDocumentID', 'AccessedAt']);
            $table->index(['PrivateDocumentID', 'AccessedAt']);
        });

        DB::connection('Portal')->statement(
            'ALTER TABLE assessment_access_log ADD CONSTRAINT assessment_access_log_one_assessment
             CHECK ((LawEnforcementDocumentID IS NULL) <> (PrivateDocumentID IS NULL))',
        );
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('assessment_access_log');
    }
};
