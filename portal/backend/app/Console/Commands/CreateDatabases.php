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
            env('DB_DATABASE_PORTAL')
        ];

        DB::purge('create_db');
    
        foreach ($databases as $database) {
            if(!$database){
                continue;
            }
            DB::statement("CREATE DATABASE IF NOT EXISTS '$database'");
            $this->info("Database '$database' created or already exists.");
        }
        
    }
}