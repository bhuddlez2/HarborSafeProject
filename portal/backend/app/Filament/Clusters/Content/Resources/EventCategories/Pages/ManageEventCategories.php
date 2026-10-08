<?php

namespace App\Filament\Clusters\Content\Resources\EventCategories\Pages;

use App\Filament\Clusters\Content\Resources\EventCategories\EventCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEventCategories extends ManageRecords
{
    protected static string $resource = EventCategoryResource::class;

    public function getBreadcrumb(): ?string
    {
        return 'Categories';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a category'),
        ];
    }
}
