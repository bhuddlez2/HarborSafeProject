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

There is no catch-all option: "Something Else" (id 8) was dropped on purpose
on 2026-10-29. Anything else goes in the form's free-text Message field.
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
        ];

        foreach ($resources as $id => $name) {
            DB::connection('Feedback')->table('resources')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }

        // Added later for Get Support's "Request this service" links, which
        // tick a type by name. Keyed on the name, not an id: staff may already
        // have added them on a given database, under any id, and a second
        // copy would show twice on the form.
        foreach (['Support Groups', 'Community Education'] as $name) {
            if (! DB::connection('Feedback')->table('resources')->where('Name', $name)->exists()) {
                DB::connection('Feedback')->table('resources')->insert(['Name' => $name, 'ChangeDate' => now()]);
            }
        }
    }
}
