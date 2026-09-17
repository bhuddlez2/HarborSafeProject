<?php

namespace Database\Seeders;

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
*/
class CountySeeder extends Seeder
{
    public function run(): void
    {
        $counties = [
            1 => 'Bradley',
            2 => 'Polk',
        ];

        foreach ($counties as $id => $name) {
            DB::connection('Feedback')->table('counties')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }
    }
}
