<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('law_enforcement_agents', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('badge_number');
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('law_enforcement_agents');
    }
};
