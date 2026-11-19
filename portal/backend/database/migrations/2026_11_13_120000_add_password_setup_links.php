<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
New accounts set their own password from an emailed link (App\Filament\Accounts\SetupLink).
Until they do, users.password is null: nobody can sign in to the account, and
the account tables show it as Pending. The set-up links' tokens get their own
table, shaped like password_reset_tokens, so each kind keeps its own lifetime
(config/auth.php). Existing accounts keep their passwords.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::connection('Portal')->create('password_setup_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('password_setup_tokens');

        // A pending account can't keep a null password once the column is
        // NOT NULL again; an unusable random hash keeps it unable to sign in.
        DB::connection('Portal')->table('users')->whereNull('password')
            ->update(['password' => Hash::make(Str::random(64))]);

        Schema::connection('Portal')->table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
