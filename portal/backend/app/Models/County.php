<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/*
A county on the resource request form's County dropdown, managed by staff on
Form Options -> Counties - except "Other" (Burchfield B25, 2026-10-28).

"Other" is built in: a migration adds it with a fixed id, so it exists on
every server, and it can't be renamed or deleted (Form Options greys those
buttons out; the model refuses too). It is for anyone outside the named
counties: Harbor Safe works with organizations in the surrounding counties
and can point them on. It is listed last in the County dropdowns (website and
staff) and reads plain "Other" on a record, never "Other County"; everywhere
else (Analytics, the Submissions filter) it is an ordinary county.
*/
class County extends BaseModel
{
    public const OTHER_ID = 999;

    public const OTHER_NAME = 'Other';

    protected $connection = 'Feedback';

    protected $table = 'counties';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'ChangeDate',
    ];

    protected static function booted(): void
    {
        static::updating(function (County $county): void {
            if ($county->isOther() && $county->isDirty('Name')) {
                throw new LogicException('The built-in "Other" county cannot be renamed.');
            }
        });

        static::deleting(function (County $county): void {
            if ($county->isOther()) {
                throw new LogicException('The built-in "Other" county cannot be deleted.');
            }
        });
    }

    public function isOther(): bool
    {
        return (int) $this->getKey() === self::OTHER_ID;
    }

    // How a record names its county: "Bradley County", or plain "Other".
    public function displayName(): string
    {
        return $this->isOther() ? self::OTHER_NAME : "{$this->Name} County";
    }

    // A County dropdown's order: "Other" last, everything else as before.
    public function scopeOtherLast(Builder $query): Builder
    {
        return $query->orderByRaw('id = ?', [self::OTHER_ID]);
    }

    // Inverse of ResourceRequestForm::county(). Used by the panel to decide
    // whether a county can still be deleted.
    public function requests()
    {
        return $this->hasMany(ResourceRequestForm::class, 'CountyID');
    }
}
