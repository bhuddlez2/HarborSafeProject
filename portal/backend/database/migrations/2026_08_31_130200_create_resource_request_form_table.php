<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->create('resource_request_form', function (Blueprint $table) {
            $table->uuid('FormID')->primary();
            $table->string('FirstName', 100);
            $table->string('LastName', 100)->nullable();
            $table->string('EmailAddress', 250);
            $table->string('SafePhoneNumber', 20);
            $table->foreignId('ResourceTypeID')->nullable()->constrained('resources');
            $table->foreignId('CountyID')->nullable()->constrained('counties');
            $table->string('Message', 1000)->nullable();
            $table->timestamp('SubmissionDate')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->dropIfExists('resource_request_form');
    }
};
