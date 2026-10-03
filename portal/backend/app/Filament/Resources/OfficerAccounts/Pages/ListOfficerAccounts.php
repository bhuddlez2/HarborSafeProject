<?php

namespace App\Filament\Resources\OfficerAccounts\Pages;

use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficerAccounts extends ListRecords
{
    protected static string $resource = OfficerAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
