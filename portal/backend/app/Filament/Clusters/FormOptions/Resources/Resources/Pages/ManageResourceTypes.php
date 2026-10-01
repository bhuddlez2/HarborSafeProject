<?php

namespace App\Filament\Clusters\FormOptions\Resources\Resources\Pages;

use App\Filament\Clusters\FormOptions\Resources\Resources\ResourceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResourceTypes extends ManageRecords
{
    protected static string $resource = ResourceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a resource type'),
        ];
    }
}
