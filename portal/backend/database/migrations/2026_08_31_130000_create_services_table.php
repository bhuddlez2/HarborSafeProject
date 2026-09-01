<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->create('services', function (Blueprint $table) {
            $table->id();
            $table->string('Name', 250);
            $table->timestamp('ChangeDate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->dropIfExists('services');
    }
};
