<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('_private_assessment', function (Blueprint $table) {
            $table->uuid('DocumentID')->primary();
            $table->timestamp('DateCreated')->useCurrent();
            $table->string('OffenderFirstName', 50);
            $table->string('OffenderLastName', 50);
            $table->string('OffenderSex', 10);           // widened from 1 to 10
            $table->date('OffenderDOB')->nullable();
            $table->string('OffenderVictimRelationship', 50);
            $table->string('VictimFirstName', 50);
            $table->string('VictimLastName', 50);
            $table->string('VictimSex', 10);              // widened from 1 to 10
            $table->date('VictimDOB')->nullable();         // now nullable
            $table->string('VictimSafePhoneNumber', 20)->nullable();
            $table->uuid('SubmitterID')->nullable();
            $table->foreign('SubmitterID')->references('SubmitterID')->on('_submitter_info');
            $table->uuid('AssessmentDocID');
            $table->foreign('AssessmentDocID')->references('AssessmentDocID')->on('_assessment_answers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('_private_assessment');
    }
};