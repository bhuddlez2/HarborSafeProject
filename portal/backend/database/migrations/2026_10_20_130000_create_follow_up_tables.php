<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
Harbor Safe's follow-up on each form (team plan task 16; roadmap Phase Z).

follow_ups: one row per form - officer assessment, civilian assessment or
resource request - holding where it stands now: its status, when the form was
submitted, who last updated it and when, and how many reminders have gone out.
It points at exactly one form (a CHECK, as in assessment_access_log): real
foreign keys for the two assessments, a plain id for the resource request,
which lives in the Feedback database. One table for all three, so the queue can
list, count and sort them together.

follow_up_entries: the history, append-only - every status change and every
note, with who and when. Status is the status after the entry; FromStatus is
set only when the entry changed it, so a note on its own has none.

Both written only through App\Services\FollowUps. Foreign keys restrict
deletion, so a form can't lose its follow-up history; nothing deletes forms.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('follow_ups', function (Blueprint $table) {
            $table->uuid('FollowUpID')->primary();

            // App\Enums\FollowUpKind
            $table->string('Kind', 30);

            $table->uuid('LawEnforcementDocumentID')->nullable()->unique();
            $table->foreign('LawEnforcementDocumentID')->references('DocumentID')->on('law_enforcement_assessment');

            $table->uuid('PrivateDocumentID')->nullable()->unique();
            $table->foreign('PrivateDocumentID')->references('DocumentID')->on('_private_assessment');

            // resource_request_form.FormID, in the Feedback database: no FK.
            $table->uuid('ResourceRequestFormID')->nullable()->unique();

            // App\Enums\FollowUpStatus
            $table->string('Status', 30)->default('needs_follow_up');

            // When the form itself was submitted (UTC), for reminders and sorting.
            $table->timestamp('SubmittedAt');

            $table->timestamp('LastUpdatedAt')->nullable();
            $table->foreignId('LastUpdatedBy')->nullable()->constrained('users');

            // How many "still needs follow-up" reminders have gone out (24 h, 48 h).
            $table->unsignedTinyInteger('RemindersSent')->default(0);

            $table->index(['Status', 'SubmittedAt']);
        });

        DB::connection('Portal')->statement(
            'ALTER TABLE follow_ups ADD CONSTRAINT follow_ups_one_form CHECK (
                (LawEnforcementDocumentID IS NOT NULL) + (PrivateDocumentID IS NOT NULL) + (ResourceRequestFormID IS NOT NULL) = 1
            )',
        );

        Schema::connection('Portal')->create('follow_up_entries', function (Blueprint $table) {
            $table->uuid('EntryID')->primary();

            $table->uuid('FollowUpID');
            $table->foreign('FollowUpID')->references('FollowUpID')->on('follow_ups');

            // Set only when this entry changed the status.
            $table->string('FromStatus', 30)->nullable();
            $table->string('Status', 30);

            $table->text('Note');
            $table->foreignId('EnteredBy')->constrained('users');
            $table->timestamp('EnteredAt')->useCurrent();

            $table->index(['FollowUpID', 'EnteredAt']);
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('follow_up_entries');
        Schema::connection('Portal')->dropIfExists('follow_ups');
    }
};
