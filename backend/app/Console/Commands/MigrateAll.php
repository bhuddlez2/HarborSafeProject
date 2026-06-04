<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;


class MigrateAll extends Command
{
    protected $signature = 'migrate:all';
    protected $description = 'Create databases and run all migrations';
    /**
     * Execute the console command.
     */
     public function handle()
    {
        // First create databases
        $this->call('db:create');

        // Then run migrations for each connection
        $connections = [
            'portal',
        ];

        foreach ($connections as $connection) {
            $this->info("Migrating: $connection");
            $this->call('migrate', [
                '--database' => $connection,
                '--force'    => true,
            ]);
        }

        $this->info('All done!');
    }
}
