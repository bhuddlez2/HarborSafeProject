<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    // spatie/laravel-permission has no connection setting and would fall back
    // to the unreachable default mariadb connection. Its tables live on Portal
    // with everything else (see Filament_CMS_Design.md §3.3).
    protected $connection = 'Portal';
}
