<?php

namespace Database\Seeders;

use App\Models\County;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
Seeds the counties lookup table - the county dropdown on the website's
resource-request form. Runs on the Feedback (full access) connection;
FeedbackPublic deliberately only has SELECT here.

Idempotent: keyed on explicit ids so re-running updates names in place rather
than duplicating rows, and so ids stay stable for submissions that already
reference them.

Currently the two counties the frontend offered. Adding to the service area
is now a row here plus a re-run of this seeder - the website reads the list
from the API, so nothing in the frontend needs to change.

Plus the built-in "Other" (County::OTHER_ID), which a migration also adds so
it exists on every server.
*/
class CountySeeder extends Seeder
{
    public function run(): void
    {
        $counties = [
            1 => 'Bradley',
            2 => 'Polk',
            County::OTHER_ID => County::OTHER_NAME,
        ];

        foreach ($counties as $id => $name) {
            DB::connection('Feedback')->table('counties')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }
    }
}
