<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
Reshapes an `events` row into the JSON the public website already consumes.

THE MOCK FILE IS THE CONTRACT, NOT THIS CLASS. The Events & News page was built
against website/frontend/src/app/lib/mock-events.json, and every component
downstream reads these exact key names. This output has to match it field for
field - see Filament_CMS_Design.md section 6.1. The accompanying test asserts
that against the mock file itself rather than against a copy of it, so the two
cannot drift apart silently.

Three things here are easy to get wrong:

  1. TIMES ARE EMITTED IN EASTERN, NOT UTC. The columns are UTC, but the mock
     carries an ISO 8601 string with the -05:00/-04:00 offset, and the site
     renders with timeZone: America/New_York. Converting here keeps the payload
     identical to the mock. Emitting a UTC "Z" string would happen to render
     the same, but it would no longer match the contract, and the next person
     comparing the two would have to work out why.

  2. A NESTED OBJECT IS NULL OR WHOLE, NEVER PARTIAL. The flattened columns
     have to be recomposed into location/image/registration, and the page
     guards on plain truthiness - `event.location && ...`. So a location with
     every column empty must be null, because {name: null} is truthy and would
     render an empty row. The has* helpers on the model exist for exactly this
     decision.

  3. `description` IS AN ARRAY, AND [] NOT NULL. The column is nullable, the
     mock always carries at least []. The page guards with `?.length` so null
     would be survivable, but the contract says array, so it gets an array.
*/
class PublicEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;

        return [
            'id' => $event->getKey(),
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description ?? [],
            'startsAt' => $this->easternIso($event->starts_at),
            'endsAt' => $this->easternIso($event->ends_at),
            'allDay' => (bool) $event->all_day,
            'recurrence' => $event->recurrence,
            'location' => $this->location($event),
            'image' => $this->image($event),
            'registration' => $this->registration($event),
            // The site renders the category as a plain string, never an id.
            // Supplied by the join in PublicEventController, not by the
            // relation - see that controller for why.
            'category' => $event->category_name ?? $event->category?->Name,
            'isPublished' => (bool) $event->is_published,
            'isCancelled' => (bool) $event->is_cancelled,
        ];
    }

    private function easternIso(mixed $value): ?string
    {
        return $value?->copy()->setTimezone(Event::DISPLAY_TIMEZONE)->toIso8601String();
    }

    /*
    Keys are conditionally present, matching the mock: a physical venue carries
    `address` and no `virtualNote`, a virtual one the reverse. The site reads
    both with plain property access guarded by a truthiness check, so an absent
    key and a null one behave identically - but the mock omits them, so this
    does too.
    */
    private function location(Event $event): ?array
    {
        if (! $event->hasLocation()) {
            return null;
        }

        $location = ['name' => $event->location_name];

        if ($event->location_is_virtual) {
            $location['isVirtual'] = true;

            if (filled($event->location_virtual_note)) {
                $location['virtualNote'] = $event->location_virtual_note;
            }

            return $location;
        }

        if (filled($event->location_address)) {
            $location['address'] = $event->location_address;
        }

        $location['isVirtual'] = false;

        return $location;
    }

    private function image(Event $event): ?array
    {
        if (! $event->hasImage()) {
            return null;
        }

        return [
            // An absolute URL to the streaming endpoint, since the website is
            // served from a different origin to the API.
            'src' => $event->imageUrl(),
            // The site does `alt={event.image.alt ?? ""}`, so null is safe -
            // but an image without a description is an accessibility problem,
            // and the panel requires alt text whenever a file is attached.
            'alt' => $event->image_alt,
        ];
    }

    private function registration(Event $event): ?array
    {
        if (! $event->hasRegistration()) {
            return null;
        }

        return [
            'url' => $event->registration_url,
            // The site falls back to "Register" when this is null.
            'label' => $event->registration_label,
        ];
    }
}
