<?php

namespace App\Filament\Resources\OfficerAccounts\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficerAccounts extends ListRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = OfficerAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
