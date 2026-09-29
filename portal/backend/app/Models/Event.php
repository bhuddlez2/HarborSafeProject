<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/*
An event on the website's Events & News page. Columns mirror
website/frontend/src/app/lib/mock-events.json, which this table replaces - see
Filament_CMS_Design.md section 6.

Three things to know before writing code against this model:

  1. starts_at/ends_at are stored UTC (config('app.timezone') is 'UTC'), so the
     datetime casts hand back Carbon instances in UTC. The site renders them in
     America/New_York (TIME_ZONE in website/frontend/src/app/lib/content.js).
     Anything that accepts a wall-clock time from staff - the panel's
     DateTimePicker especially - has to convert on the way in, or events either
     side of a DST boundary land an hour off.

  2. description is an array of paragraph strings, not HTML. The site does
     description.map(...), so HTML from a rich-text editor would render as
     visible escaped markup. The json cast gives you a PHP array.

  3. The nested location/image/registration objects the site expects are
     flattened into columns here. When every column behind one of them is null
     the API has to emit null for the whole object, because the page guards on
     plain truthiness - {name: null, address: null} is truthy and renders an
     empty row. The has* helpers below are there to make that check explicit.

is_published and is_cancelled are independent: a published event can also be
cancelled, which the page renders as a "cancelled" badge with the registration
link suppressed. They are not one status field.

image_path is an object-storage key, not a URL. Turning it into the absolute
URL the site wants at image.src is the API layer's job.
*/
class Event extends BaseModel
{
    use HasUuids;

    protected $connection = 'Content';

    protected $table = 'events';

    protected $primaryKey = 'EventID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'title',
        'summary',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'recurrence',
        'location_name',
        'location_address',
        'location_is_virtual',
        'location_virtual_note',
        'image_path',
        'image_alt',
        'registration_url',
        'registration_label',
        'category_id',
        'is_published',
        'is_cancelled',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'location_is_virtual' => 'boolean',
            'category_id' => 'integer',
            'is_published' => 'boolean',
            'is_cancelled' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    // What the public endpoint serves: published only, soonest first. The site
    // sorts client-side as well, but ordering here keeps the index on
    // (is_published, starts_at) doing the work.
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('starts_at');
    }

    // Used by the API layer to decide whether to emit a nested object or null.
    public function hasLocation(): bool
    {
        return filled($this->location_name)
            || filled($this->location_address)
            || filled($this->location_virtual_note)
            || $this->location_is_virtual;
    }

    public function hasImage(): bool
    {
        return filled($this->image_path);
    }

    public function hasRegistration(): bool
    {
        return filled($this->registration_url);
    }
}
