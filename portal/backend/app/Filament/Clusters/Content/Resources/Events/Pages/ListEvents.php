<?php

namespace App\Filament\Clusters\Content\Resources\Events\Pages;

use App\Filament\Clusters\Content\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    // Suppress the "List" segment so the breadcrumb reads "Content > Events"
    // rather than "Content > Events > List" (which appears because Events has
    // separate create/edit pages, unlike Newsletters which uses ManageRecords).
    public function getBreadcrumb(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New event'),
        ];
    }
}
