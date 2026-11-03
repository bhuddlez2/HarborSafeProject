<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Feedback-connection lookup tables backing the website's two public
        // forms. Safe to run on their own:
        //   php artisan db:seed --class=ServiceSeeder
        $this->call([
            ServiceSeeder::class,
            ResourceSeeder::class,
            CountySeeder::class,
        ]);

        // Content-connection lookup backing the events page's category badge.
        $this->call([
            EventCategorySeeder::class,
        ]);
    }
}
