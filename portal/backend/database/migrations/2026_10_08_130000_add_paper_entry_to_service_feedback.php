<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Service-feedback forms filled in on the panel by an admin or secretary -
typically ones that arrived on paper ("New feedback form" on the Service
feedback tab).

service_feedback gains:
  - Source: 'online' (the website's form - the default, so the public
    endpoint and every existing row need no change) or 'staff' (filled in on
    the panel - see ServiceFeedback::SOURCE_STAFF; first written as 'paper',
    renamed before release).
  - EnteredBy: the staff user who typed it in. users lives on Portal, a
    different database, so this cannot be a real foreign key.
  - ScanFileID: an optional scan of the original form, in feedback_scans.

SubmissionDate keeps its useCurrent() default for online feedback; a staff
entry stores the date and time entered on its form, in the same database-server
time (ServiceFeedback::databaseNow()).

feedback_scans mirrors content_files (see that migration for the LONGBLOB and
max_allowed_packet notes, which apply unchanged). It is a separate table on
this database rather than rows in content_files so that scans stay beside the
feedback, out of the content database, and away from its broader file route -
see App\Models\FeedbackScan.

The website's restricted MySQL user needs NO new grant: it only inserts online
feedback, and must never read a scan.
*/
return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->create('feedback_scans', function (Blueprint $table) {
            $table->uuid('FileID')->primary();
            $table->string('name', 255);
            $table->string('mime_type', 127);
            $table->unsignedInteger('size_bytes');
            $table->longText('contents')->charset('binary');
            $table->char('checksum', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('Feedback')->table('service_feedback', function (Blueprint $table) {
            $table->string('Source', 10)->default('online')->after('SubmissionDate');
            $table->unsignedBigInteger('EnteredBy')->nullable()->after('Source');
            $table->uuid('ScanFileID')->nullable()->after('EnteredBy');
            $table->foreign('ScanFileID')->references('FileID')->on('feedback_scans');
            $table->index('Source');
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->table('service_feedback', function (Blueprint $table) {
            $table->dropForeign(['ScanFileID']);
            $table->dropIndex(['Source']);
            $table->dropColumn(['Source', 'EnteredBy', 'ScanFileID']);
        });

        Schema::connection('Feedback')->dropIfExists('feedback_scans');
    }
};
