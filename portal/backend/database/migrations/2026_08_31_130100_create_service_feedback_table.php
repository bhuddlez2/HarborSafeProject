<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->create('service_feedback', function (Blueprint $table) {
            $table->uuid('FormID')->primary();
            $table->foreignId('ServiceID')->constrained('services');
            $table->unsignedTinyInteger('Rating');
            $table->string('Comment', 1000)->nullable();
            $table->timestamp('SubmissionDate')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->dropIfExists('service_feedback');
    }
};
