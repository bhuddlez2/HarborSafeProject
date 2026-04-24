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
            $table->string('SubmitterEmail',100)->nullable();
            $table->string('SubmitterPhoneNumber',20)->nullable();
            $table->string('SubmitterFirstName', 50)->nullable();
            $table->string('SubmitterLastName',50)->nullable();
            $table->string('OffenderFirstName',50);
            $table->string('OffenderLastName',50);
            $table->string('OffenderSex',1);
            $table->date('OffenderDOB')->nullable();
            $table->string('OffenderVictimRelationship');
            $table->string('VictimFirstName',50);
            $table->string('VictimLastName',50);
            $table->string('VictimSex',1);
            $table->date('VictimDOB');
            $table->string('VictimSafePhoneNumber',20)->nullable();
            $table->foreignId('AssessmentDocID')->constrained();        //Actual assessment doc #
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
