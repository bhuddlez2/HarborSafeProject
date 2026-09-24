<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('assessment_answer_change_log', function (Blueprint $table) {
            $table->uuid('LogID')->primary();
            $table->uuid('AssessmentDocID');
            $table->foreign('AssessmentDocID')->references('AssessmentDocID')->on('_assessment_answers');
            $table->string('ChangeField', 32);
            $table->boolean('PreviousValue')->nullable();
            $table->boolean('NewValue')->nullable();
            $table->foreignId('ChangedBy')->constrained('users');
            $table->timestamp('TimeStamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('assessment_answer_change_log');
    }
};
