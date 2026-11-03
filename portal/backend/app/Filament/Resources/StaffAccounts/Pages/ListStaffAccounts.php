<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffAccounts extends ListRecords
{
    use CapsTableSearch;
    use BreadcrumbNamesThePage;

    protected static string $resource = StaffAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Account'),
        ];
    }
}
