<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicEventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
GET /api/public/events - the published events feed for the public website.

Reads through the restricted `ContentPublic` connection, never the full-access
`Content` one, matching how ServiceController and the other public endpoints
behave. That connection currently carries root credentials in .env, so the
restriction is not yet real; creating the SELECT-only
`harborsafe_content_public` user (Filament_CMS_Design.md section 6.5) is then
purely a credentials change with no code change here.

WHY A JOIN AND NOT ->with('category'). Eloquent resolves a relation on the
RELATED model's connection, and EventCategory declares `Content`. So eager
loading would quietly read the category through the full-access connection
while the events themselves came through the restricted one - defeating the
point, and leaving a second query that the restricted user would never be
tested against. One joined query keeps everything on one connection.

No pagination. The site's Events & News page sorts and filters the whole list
client-side, and this is a few dozen rows for a single organisation; paginating
would change the response shape the page is built against for no benefit.
*/
class PublicEventController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $events = Event::on('ContentPublic')
            ->where('events.is_published', true)
            ->leftJoin('event_categories', 'events.category_id', '=', 'event_categories.id')
            // events.* first so a column never collides with the joined name;
            // category_name is what PublicEventResource reads.
            ->select('events.*', 'event_categories.Name as category_name')
            ->orderBy('events.starts_at')
            ->get();

        return PublicEventResource::collection($events);
    }
}
