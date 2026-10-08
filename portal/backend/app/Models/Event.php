<?php

namespace App\Models;

use App\Support\Timezones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;

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

image_path is a path on the configured filesystem disk, not a URL. Turning it
into the absolute URL the site wants at image.src is the API layer's job. The
column is storage-agnostic, so which disk is in use is a config concern.
*/
class Event extends BaseModel
{
    use HasUuids;

    /*
    The wall-clock timezone the public site renders event times in - it must
    match TIME_ZONE in website/frontend/src/app/lib/content.js.

    starts_at/ends_at are stored UTC. Anything that shows a time to a human,
    or accepts one from a human, converts through this. It lives here so the
    panel's date pickers and the public API agree on one value; the hazard is
    a DST boundary, where a hard-coded -05:00 or -04:00 silently shifts half
    the year's events by an hour.
    */
    // The project-wide display zone - see App\Support\Timezones.
    public const DISPLAY_TIMEZONE = Timezones::DISPLAY;

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
        'image_file_id',
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

    // The uploaded image, stored as a row in content_files rather than on a
    // disk. Safe to eager-load: ContentFile's global scope keeps the blob out.
    public function imageFile()
    {
        return $this->belongsTo(ContentFile::class, 'image_file_id', 'FileID');
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
        return filled($this->image_file_id) || filled($this->image_path);
    }

    /*
    The absolute URL the website wants at image.src, or null.

    Two storage paths are live by design (see the
    add_content_file_ids_to_events_and_newsletters migration): a row in
    content_files, which is what the panel writes today, or a path on a
    filesystem disk, which is the documented fallback. image_file_id wins when
    both are somehow set.
    */
    public function imageUrl(): ?string
    {
        if (filled($this->image_file_id)) {
            return route('public.content-files.show', ['file' => $this->image_file_id]);
        }

        return filled($this->image_path)
            ? Storage::disk(config('filesystems.default'))->url($this->image_path)
            : null;
    }

    public function hasRegistration(): bool
    {
        return filled($this->registration_url);
    }
}
