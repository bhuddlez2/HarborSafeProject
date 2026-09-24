<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('assessment_change_log', function (Blueprint $table) {
            $table->uuid('ChangeLogID')->primary();
            $table->uuid('DocumentID');
            $table->foreign('DocumentID')->references('DocumentID')->on('law_enforcement_assessment');
            $table->string('ChangeField', 32);
            $table->string('PreviousValue', 50)->nullable();
            $table->string('NewValue', 50)->nullable();
            $table->foreignId('ChangedBy')->constrained('users');
            $table->timestamp('TimeStamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('assessment_change_log');
    }
};
