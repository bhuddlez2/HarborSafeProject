<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('_submitter_info', function (Blueprint $table) {
            $table->uuid('SubmitterID')->primary();
            $table->string('SubmitterEmail', 100)->nullable();
            $table->string('SubmitterPhoneNumber', 20)->nullable();
            $table->string('SubmitterFirstName', 50)->nullable();
            $table->string('SubmitterLastName', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('_submitter_info');
    }
};  