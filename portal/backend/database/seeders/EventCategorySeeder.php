<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
Seeds the event_categories lookup - the category badge on the website's
Events & News page. Runs on the Content (full access) connection;
ContentPublic deliberately only has SELECT here.

The five values are the ones already used in
website/frontend/src/app/lib/mock-events.json, so the seeded data matches what
the page was built against.

Idempotent: keyed on explicit ids so re-running updates names in place rather
than duplicating rows, and so ids stay stable for events that already
reference them.
*/
class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            1 => 'Support Group',
            2 => 'Fundraiser',
            3 => 'Training',
            4 => 'Volunteer',
            5 => 'Community',
        ];

        foreach ($categories as $id => $name) {
            DB::connection('Content')->table('event_categories')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }
    }
}
