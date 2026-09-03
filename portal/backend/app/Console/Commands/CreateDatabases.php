<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateDatabases extends Command
{
    protected $signature = "db:create";
    protected $description = 'Create all databases if they do not exist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databases = [
            env('DB_DATABASE_PORTAL'),
            env('DB_DATABASE_FEEDBACK'),
        ];

        // Issued via the Portal connection rather than the default
        // connection: the default 'mariadb' connection isn't configured
        // with working credentials in this project, but Portal's user has
        // the server-level privilege to create sibling databases regardless
        // of which database it's currently pointed at.
        foreach ($databases as $database) {
            if (!$database) {
                continue;
            }
            DB::connection('Portal')->statement("CREATE DATABASE IF NOT EXISTS `$database`");
            $this->info("Database '$database' created or already exists.");
        }
    }
}
