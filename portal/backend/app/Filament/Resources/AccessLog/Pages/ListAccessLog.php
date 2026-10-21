<?php

namespace App\Filament\Resources\AccessLog\Pages;

use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Resources\AccessLog\AccessLogResource;
use Filament\Resources\Pages\ListRecords;

// No header actions: rows are only ever written by AssessmentAccessLog::record().
class ListAccessLog extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = AccessLogResource::class;
}
