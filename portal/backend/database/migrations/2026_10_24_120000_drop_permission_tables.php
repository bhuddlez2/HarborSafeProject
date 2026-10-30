<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
Drops spatie/laravel-permission's five tables. Filament Shield, which brought
the package in, was removed on 2026-10-24 without ever being used: no role or
permission was ever created or assigned, and every screen decides access on
users.role (App\Enums\UserRole) and lists the actions it allows
(App\Filament\Concerns\AllowsOnlyListedActions; ActionAccessTest).

The migration that created them (2026_09_23_231927) stays, as every migration
that has run does. down() puts the tables back exactly as it made them.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        // Pivot tables first: they hold the foreign keys.
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $table) {
            Schema::connection('Portal')->dropIfExists($table);
        }
    }

    public function down(): void
    {
        if (Schema::connection('Portal')->hasTable('permissions')) {
            return;
        }

        (require __DIR__.'/2026_09_23_231927_create_permission_tables.php')->up();
    }
};
