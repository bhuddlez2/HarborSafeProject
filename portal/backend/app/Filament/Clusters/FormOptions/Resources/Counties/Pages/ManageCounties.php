<?php

namespace App\Filament\Clusters\FormOptions\Resources\Counties\Pages;

use App\Filament\Clusters\FormOptions\Resources\Counties\CountyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCounties extends ManageRecords
{
    protected static string $resource = CountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a county'),
        ];
    }
}
