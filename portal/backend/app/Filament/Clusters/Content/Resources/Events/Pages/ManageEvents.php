<?php

namespace App\Filament\Clusters\Content\Resources\Events\Pages;

use App\Filament\Clusters\Content\Resources\Events\EventResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEvents extends ManageRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New event'),
        ];
    }
}
