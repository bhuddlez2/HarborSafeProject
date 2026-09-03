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

        // Portal's migrations table is this project's single canonical
        // ledger, even for migrations whose Schema::connection(...) call
        // routes their DDL to the Feedback database. Do NOT add 'Feedback'
        // here — running `migrate --database=Feedback` against a database
        // with an empty migrations table makes Laravel try to replay the
        // *entire* migration history into it, not just the Feedback-specific
        // ones, since tracking is per-database. (This was hit and fixed
        // once already while building the Feedback schema.)
        $connections = [
            'Portal',
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
