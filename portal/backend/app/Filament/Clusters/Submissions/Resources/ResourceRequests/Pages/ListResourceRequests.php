<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages;

use App\Enums\FollowUpKind;
use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Models\FollowUp;
use App\Models\ResourceRequestForm;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

// No create action: requests arrive from the public form, never from staff.
class ListResourceRequests extends ListRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = ResourceRequestResource::class;

    // Resource request follow-ups by form id, loaded once per request
    // (private, so Livewire never keeps it between requests).
    private ?Collection $followUps = null;

    /*
    A request's follow-up, for the Follow-Up column and button (Burchfield
    B58). follow_ups is on Portal and requests on Feedback, so it is looked
    up rather than joined. Scoped by FollowUp::visibleTo(): null for anyone
    who doesn't work resource requests, and for a request with no follow-up.
    */
    public function followUpFor(ResourceRequestForm $request): ?FollowUp
    {
        $this->followUps ??= FollowUp::query()
            ->visibleTo(Filament::auth()->user())
            ->where('Kind', FollowUpKind::ResourceRequest->value)
            ->whereNotNull('ResourceRequestFormID')
            ->get()
            ->keyBy('ResourceRequestFormID');

        return $this->followUps->get($request->getKey());
    }
}
