<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/*
A newsletter issue listed on the website's Events & News page. Columns mirror
website/frontend/src/app/lib/mock-newsletters.json, which this table replaces -
see Filament_CMS_Design.md section 6.

file_size_bytes and file_pages are nullable and stored rather than derived, so
listing issues doesn't mean stat-ing every object in the bucket. The site
renders them conditionally ("PDF - 2.3 MB - 4 pages", either half dropped when
missing), so leaving them null is a supported state, not a data problem.

file_path is a path on the configured filesystem disk, not a URL. The API layer
turns it into the site's file.url. Which disk matters here: anything on the
public disk is reachable by URL to whoever guesses the filename, so an issue
that must not be openly readable belongs on the private disk behind a route
that checks authorization - see Filament_CMS_Design.md section 6.6, where that
per-issue decision is still open.
*/
class Newsletter extends BaseModel
{
    use HasUuids;

    protected $connection = 'Content';

    protected $table = 'newsletters';

    protected $primaryKey = 'NewsletterID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'title',
        'issue_date',
        'summary',
        'file_path',
        'file_size_bytes',
        'file_pages',
        'is_published',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    protected function casts(): array
    {
        return [
            // A calendar month, not a moment - no timezone handling needed
            // here, unlike Event's starts_at/ends_at.
            'issue_date' => 'date',
            'file_size_bytes' => 'integer',
            'file_pages' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    // What the public endpoint serves: published only, newest issue first,
    // matching getNewsletters() in website/frontend/src/app/lib/content.js.
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderByDesc('issue_date');
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }
}
