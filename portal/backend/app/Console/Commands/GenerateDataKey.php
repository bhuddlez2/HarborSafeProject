<?php

namespace App\Console\Commands;

use App\Support\SensitiveData;
use Illuminate\Console\Command;

/*
Creates DATA_ENCRYPTION_KEY, the key that encrypts sensitive records
(App\Support\SensitiveData), on a new install:

  php artisan data-key:generate          writes it into .env
  php artisan data-key:generate --show   only prints one, for a server whose
                                         .env is edited by hand

It never replaces a key that is already set: the records in the database can
only be read with the key they were encrypted with. To change keys, put the old
one in DATA_ENCRYPTION_PREVIOUS_KEYS (comma-separated) and the new one in
DATA_ENCRYPTION_KEY by hand; old records still open, new ones use the new key.
*/
class GenerateDataKey extends Command
{
    protected $signature = 'data-key:generate {--show : Print a key without writing it anywhere}';

    protected $description = 'Create DATA_ENCRYPTION_KEY, the key for encrypted records (never replaces one)';

    public function handle(): int
    {
        $key = SensitiveData::generateKey();

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        if (filled(config('app.data_key'))) {
            $this->error(SensitiveData::KEY_ENV.' is already set. It is never replaced: records encrypted with it could no longer be read.');

            return self::FAILURE;
        }

        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            $this->error("No writable .env at {$path}. Run with --show and add the key by hand.");

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        $line = SensitiveData::KEY_ENV.'='.$key;
        $pattern = '/^'.SensitiveData::KEY_ENV.'=.*$/m';

        // An empty "DATA_ENCRYPTION_KEY=" line is filled in; otherwise the key
        // is added at the end.
        $env = preg_match($pattern, $env)
            ? (string) preg_replace($pattern, $line, $env, 1)
            : rtrim($env, "\r\n").PHP_EOL.PHP_EOL.$line.PHP_EOL;

        file_put_contents($path, $env);

        $this->info(SensitiveData::KEY_ENV.' written to .env. Back it up somewhere safe: without it, encrypted records can never be read.');

        return self::SUCCESS;
    }
}
