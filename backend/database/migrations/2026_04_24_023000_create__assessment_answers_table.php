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
        Schema::create('_assessment_answers', function (Blueprint $table) {
            $table->uuid('AssessmentDocID')->primary();
            $table->boolean('RiskIndicator1');
            $table->boolean('RiskIndicator2');
            $table->boolean('RiskIndicator3');
            $table->boolean('RiskIndicator4');
            $table->boolean('RiskIndicator5');
            $table->boolean('RiskIndicator6');
            $table->boolean('RiskIndicator7');
            $table->boolean('RiskIndicator8');
            $table->boolean('RiskIndicator9');
            $table->boolean('RiskIndicator10');
            $table->boolean('RiskIndicator11');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_assessment_answers');
    }
};
