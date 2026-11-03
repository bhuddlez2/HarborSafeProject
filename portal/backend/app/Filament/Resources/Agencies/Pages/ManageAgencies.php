<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Resources\Agencies\AgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAgencies extends ManageRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add an Agency'),
        ];
    }
}
