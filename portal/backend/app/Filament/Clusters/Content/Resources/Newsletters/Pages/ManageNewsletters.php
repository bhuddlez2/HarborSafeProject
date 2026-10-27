<?php

namespace App\Filament\Clusters\Content\Resources\Newsletters\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Clusters\Content\Resources\Newsletters\NewsletterResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use Filament\Resources\Pages\ManageRecords;

// ManageRecords: create and edit open as wizard pop-ups on this page. Create
// saves only from the last step (NewsletterResource::createAction()).
class ManageNewsletters extends ManageRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NewsletterResource::createAction(),
        ];
    }
}
