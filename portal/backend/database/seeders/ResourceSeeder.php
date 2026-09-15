<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
Seeds the resources lookup table - the "Resources of interest" checkboxes on
the website's resource-request form. Runs on the Feedback (full access)
connection; FeedbackPublic deliberately only has SELECT here.

Idempotent: keyed on explicit ids so re-running updates names in place rather
than duplicating rows, and so ids stay stable for the
resource_request_resource_types rows that already reference them.

This list and the services list are independent vocabularies - the same
wording appearing in both (e.g. "Emergency Shelter") is intentional.

"Something Else" is the catch-all; the form's free-text Message field is
where the detail for those ends up, since there's no per-option text input.
*/
class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            1 => 'Emergency Shelter',
            2 => 'Safety Planning',
            3 => 'Orders of Protection',
            4 => 'Counseling',
            5 => 'Legal Help',
            6 => 'Transportation',
            7 => 'Clothing',
            8 => 'Something Else',
        ];

        foreach ($resources as $id => $name) {
            DB::connection('Feedback')->table('resources')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }
    }
}
