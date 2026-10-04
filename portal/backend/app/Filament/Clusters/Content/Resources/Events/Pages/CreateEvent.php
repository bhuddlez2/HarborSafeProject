<?php

namespace App\Filament\Clusters\Content\Resources\Events\Pages;

use App\Filament\Clusters\Content\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
