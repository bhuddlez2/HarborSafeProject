<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Drops Laravel Sanctum's API token table. Sanctum was removed with the
signed-in API routes (team plan task 29): staff sign in to the Filament panel
with normal session cookies, nothing ever issued a token, and the table was
always empty.

The migration that created it (2026_04_07_174817) stays, because migrations
that already ran are never edited or deleted here, and
2026_10_08_140000_restore_utc_for_timestamps_written_by_laravel expects the
table to exist when it runs. On a fresh database the table is created and
then dropped here; everywhere, it ends up gone.

down() puts it back exactly as it was.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('Portal')->dropIfExists('personal_access_tokens');
    }

    public function down(): void
    {
        if (Schema::connection('Portal')->hasTable('personal_access_tokens')) {
            return;
        }

        Schema::connection('Portal')->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
};
