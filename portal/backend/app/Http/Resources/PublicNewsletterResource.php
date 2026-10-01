<?php

namespace App\Http\Resources;

use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
Reshapes a `newsletters` row into the JSON the public website already consumes.

As with PublicEventResource, website/frontend/src/app/lib/mock-newsletters.json
is the contract and the accompanying test asserts against that file directly.

Two notes:

  - `issueDate` is a plain calendar date ("2026-07-01"), not a timestamp. The
    column is a DATE and the site formats it with timeZone: "UTC" precisely so
    a month label cannot slip backwards over a timezone boundary. Do not turn
    this into an ISO 8601 datetime.

  - `file` is null when no PDF has been attached. The mock always carries an
    object, but every read on the site is guarded (`issue.file?.url ?? "#"`,
    `issue.file?.pages`), so null is handled - and it is more honest than
    inventing a `url: "#"` placeholder the way the mock did.
*/
class PublicNewsletterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Newsletter $newsletter */
        $newsletter = $this->resource;

        return [
            'id' => $newsletter->getKey(),
            'title' => $newsletter->title,
            'issueDate' => $newsletter->issue_date?->toDateString(),
            'summary' => $newsletter->summary,
            'file' => $this->file($newsletter),
            'isPublished' => (bool) $newsletter->is_published,
        ];
    }

    private function file(Newsletter $newsletter): ?array
    {
        if (! $newsletter->hasFile()) {
            return null;
        }

        return [
            // Absolute, since the website is served from another origin.
            'url' => $newsletter->fileUrl(),
            // Both nullable by design: the site renders them conditionally
            // ("PDF · 2.3 MB · 4 pages", either half dropped when missing).
            // sizeBytes is filled in from the stored file; pages is typed by
            // staff, because it cannot be known without parsing the PDF.
            'sizeBytes' => $newsletter->file_size_bytes,
            'pages' => $newsletter->file_pages,
        ];
    }
}
