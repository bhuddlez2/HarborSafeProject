<?php

namespace App\Models;

/*
Lookup table behind the events page's category badge. Same shape and
conventions as the County/Resource/Service lookups on the Feedback connection.

The public API must serialize Name, not the id: the website renders the
category as a plain string (CategoryBadge in
website/frontend/src/app/events/EventsContent.js).
*/
class EventCategory extends BaseModel
{
    protected $connection = 'Content';

    protected $table = 'event_categories';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'ChangeDate',
    ];

    protected function casts(): array
    {
        return [
            'ChangeDate' => 'datetime',
        ];
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
