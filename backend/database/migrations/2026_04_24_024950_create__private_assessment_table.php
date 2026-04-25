<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('_private_assessment', function (Blueprint $table) {
            $table->uuid('DocumentID')->primary();
            $table->timestamp('DateCreated')->useCurrent();
            $table->string('OffenderFirstName',50);
            $table->string('OffenderLastName',50);
            $table->string('OffenderSex',1);
            $table->date('OffenderDOB')->nullable();
            $table->string('OffenderVictimRelationship',50);
            $table->string('VictimFirstName',50);
            $table->string('VictimLastName',50);
            $table->string('VictimSex',1);
            $table->date('VictimDOB');
            $table->string('VictimSafePhoneNumber',20)->nullable();
            $table->foreignUuid('SubmitterID')->nullable()->constrained('submitter_info_table');
            $table->foreignUuid('AssessmentDocID')->constrained('assessment_answers_table');        //Actual assessment doc #
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_private_assessment');
    }
};
