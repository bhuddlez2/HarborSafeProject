<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages;

use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use Filament\Resources\Pages\ListRecords;

// No create action: requests arrive from the public form, never from staff.
class ListResourceRequests extends ListRecords
{
    protected static string $resource = ResourceRequestResource::class;
}
