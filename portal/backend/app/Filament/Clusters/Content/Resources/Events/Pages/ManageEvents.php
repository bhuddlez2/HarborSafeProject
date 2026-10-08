<?php

namespace App\Filament\Clusters\Content\Resources\Events\Pages;

use App\Filament\Clusters\Content\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEvents extends ManageRecords
{
    protected static string $resource = EventResource::class;

    public function getBreadcrumb(): ?string
    {
        return 'Events';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New event'),
        ];
    }
}
