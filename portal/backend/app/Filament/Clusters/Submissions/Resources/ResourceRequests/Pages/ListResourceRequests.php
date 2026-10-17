<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages;

use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Resources\Pages\ListRecords;

// No create action: requests arrive from the public form, never from staff.
class ListResourceRequests extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = ResourceRequestResource::class;
}
