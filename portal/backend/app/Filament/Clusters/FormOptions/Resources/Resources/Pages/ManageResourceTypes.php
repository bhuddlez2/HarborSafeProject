<?php

namespace App\Filament\Clusters\FormOptions\Resources\Resources\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Clusters\FormOptions\Resources\Resources\ResourceTypeResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResourceTypes extends ManageRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = ResourceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a Resource Type'),
        ];
    }
}
