<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
Seeds the services lookup table - the options in the website's "Share Your
Feedback" form. Runs on the Feedback (full access) connection; FeedbackPublic
deliberately only has SELECT here.

Idempotent: keyed on explicit ids so re-running updates names in place rather
than duplicating rows, and so ids stay stable for submissions that already
reference them.

This list and the resources list are independent vocabularies - the same
wording appearing in both (e.g. "Emergency Shelter") is intentional, not a
duplicate to be deduplicated.
*/
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            1 => 'Emergency Shelter',
            2 => 'Crisis Counseling',
            3 => 'Support Groups',
            4 => 'Court Advocacy',
            5 => 'Community Education',
            6 => '24/7 Crisis Line',
        ];

        foreach ($services as $id => $name) {
            DB::connection('Feedback')->table('services')->updateOrInsert(
                ['id' => $id],
                ['Name' => $name, 'ChangeDate' => now()]
            );
        }
    }
}
