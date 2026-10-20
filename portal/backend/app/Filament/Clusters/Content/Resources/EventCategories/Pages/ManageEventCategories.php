<?php

namespace App\Filament\Clusters\Content\Resources\EventCategories\Pages;

use App\Filament\Clusters\Content\Resources\EventCategories\EventCategoryResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEventCategories extends ManageRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a category'),
        ];
    }
}
