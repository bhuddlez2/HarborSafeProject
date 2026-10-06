<?php

namespace App\Models\Concerns;

use LogicException;

// For audit rows: they are written once and never changed or removed through
// Eloquent. (Test cleanup deletes through the query builder, which skips
// model events, and that is the only sanctioned way rows ever go.)
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' rows are append-only.'));
        static::deleting(fn () => throw new LogicException(static::class.' rows are append-only.'));
    }
}
