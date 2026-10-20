<?php

namespace App\Filament\Clusters\Content\Resources\Newsletters\Pages;

use App\Filament\Clusters\Content\Resources\Newsletters\NewsletterResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

// ManageRecords: create and edit open as wizard pop-ups on this page.
class ManageNewsletters extends ManageRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add an issue'),
        ];
    }
}
