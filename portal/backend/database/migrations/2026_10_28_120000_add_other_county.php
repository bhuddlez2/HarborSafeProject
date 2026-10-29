<?php

use App\Models\County;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
The built-in "Other" county (Burchfield B25, 2026-10-28): the last choice in
the resource request form's County dropdown, for anyone outside the named
counties. Added here rather than only in CountySeeder so it exists on every
server, with the fixed id the code knows it by (County::OTHER_ID). Staff can't
rename or delete it on Form Options.

down() removes it only while no request has chosen it.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        DB::connection('Feedback')->table('counties')->updateOrInsert(
            ['id' => County::OTHER_ID],
            ['Name' => County::OTHER_NAME, 'ChangeDate' => now()],
        );
    }

    public function down(): void
    {
        $feedback = DB::connection('Feedback');

        if (! $feedback->table('resource_request_form')->where('CountyID', County::OTHER_ID)->exists()) {
            $feedback->table('counties')->where('id', County::OTHER_ID)->delete();
        }
    }
};
