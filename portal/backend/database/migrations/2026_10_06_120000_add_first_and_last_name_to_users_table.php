<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
Splits a user's name into first and last, 50 characters each. From here on
the two parts are the source of truth: every account screen and the Account
page edit them, and User::booted() rebuilds `name` as "First Last" on every
save. `name` stays a real column because the assessment tables search and
sort on it, and the sidebar and officer screens display it.

Existing rows are backfilled by splitting `name` at its first space, so every
displayed name comes out unchanged; a wrong split can be corrected on the
Accounts or Officers screen. The columns are nullable only so a one-word
legacy name can have no last name - every form requires both.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->table('users', function (Blueprint $table) {
            $table->string('first_name', 50)->nullable()->after('name');
            $table->string('last_name', 50)->nullable()->after('first_name');
        });

        $portal = DB::connection('Portal');

        $portal->table('users')->select(['id', 'name'])->orderBy('id')->each(function (object $user) use ($portal): void {
            [$first, $last] = array_pad(preg_split('/\s+/', trim((string) $user->name), 2), 2, '');

            $portal->table('users')->where('id', $user->id)->update([
                'first_name' => $first === '' ? null : mb_substr($first, 0, 50),
                'last_name' => $last === '' ? null : mb_substr($last, 0, 50),
            ]);
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
