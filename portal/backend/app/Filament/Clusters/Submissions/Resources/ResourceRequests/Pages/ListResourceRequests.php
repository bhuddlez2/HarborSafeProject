<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Resources\Pages\ListRecords;

// No create action: requests arrive from the public form, never from staff.
class ListResourceRequests extends ListRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = ResourceRequestResource::class;
}
