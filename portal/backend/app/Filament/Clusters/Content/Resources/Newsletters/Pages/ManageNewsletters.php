<?php

namespace App\Filament\Clusters\Content\Resources\Newsletters\Pages;

use App\Filament\Clusters\Content\Resources\Newsletters\NewsletterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/*
ManageRecords rather than separate list/create/edit pages: a newsletter is a
title, a date, a summary and a file, which fits a modal comfortably. Events get
full pages because their form has six sections.
*/
class ManageNewsletters extends ManageRecords
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add an issue'),
        ];
    }
}
