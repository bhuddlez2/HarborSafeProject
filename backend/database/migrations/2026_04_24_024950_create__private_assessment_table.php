<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Portal';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('Portal')->create('private_assessment', function (Blueprint $table) {
            $table->uuid('DocumentID')->primary();
            $table->timestamp('DateCreated')->useCurrent();
            $table->string('OffenderFirstName', 50);
            $table->string('OffenderLastName', 50);
            $table->string('OffenderSex', 10);           // widened from 1 to 10
            $table->date('OffenderDOB')->nullable();
            $table->string('OffenderVictimRelationship',50);
            $table->string('VictimFirstName',50);
            $table->string('VictimLastName',50);
            $table->string('VictimSex',1);
            $table->date('VictimDOB');
            $table->string('VictimSafePhoneNumber',20)->nullable();
            $table->foreignUuid('SubmissionID')->nullable()->references('SubmissionID')->on('submitter_info');
            $table->foreignUuid('AssessmentDocID')->references('AssessmentDocID')->on('assessment_answers');        //Actual assessment doc #
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_assessment');
    }
};