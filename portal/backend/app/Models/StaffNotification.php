<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Notifications\DatabaseNotification;

// One alert in a staff member's bell (App\Enums\NewSubmission). Laravel's own
// notification model would use the default connection, which is the
// unreachable `mariadb` one (see CLAUDE.md), so this pins Portal, where the
// table and the users are. User::notifications() returns these.
class StaffNotification extends DatabaseNotification
{
    use Prunable;

    // How long an alert stays in the bell once it has been read.
    public const KEPT_AFTER_READ_HOURS = 12;

    protected $connection = 'Portal';

    protected $table = 'notifications';

    // Unread alerts, and read ones still inside the 12-hour window: what the
    // bell lists.
    public function scopeShownInBell(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner): Builder => $inner
            ->whereNull('read_at')
            ->orWhere('read_at', '>=', now()->subHours(self::KEPT_AFTER_READ_HOURS)));
    }

    // Read alerts past the window, removed by `php artisan model:prune`.
    public function prunable(): Builder
    {
        return static::query()->where('read_at', '<', now()->subHours(self::KEPT_AFTER_READ_HOURS));
    }
}
