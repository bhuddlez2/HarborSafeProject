<?php

namespace App\Filament\Concerns;

/*
The breadcrumb for every list page in the panel: the cluster, if the page has
one, then the page's own name - "Form options > Services", "Submissions >
Resource requests", or just "Assessment review" - never Filament's trailing
"List".

The name is the resource's navigation label, the same words as its sidebar item
or cluster tab, so the breadcrumb cannot disagree with them. Without this a
ManageRecords page in a cluster shows only the cluster name, and a ListRecords
page shows "Resource > List".
*/
trait BreadcrumbNamesThePage
{
    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }

    public function getBreadcrumb(): ?string
    {
        return static::getResource()::getNavigationLabel();
    }
}
