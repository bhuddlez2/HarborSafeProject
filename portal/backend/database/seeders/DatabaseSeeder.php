<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Feedback-connection lookup tables backing the website's two public
        // forms. Safe to run on their own:
        //   php artisan db:seed --class=ServiceSeeder
        $this->call([
            ServiceSeeder::class,
            ResourceSeeder::class,
            CountySeeder::class,
        ]);
    }
}
