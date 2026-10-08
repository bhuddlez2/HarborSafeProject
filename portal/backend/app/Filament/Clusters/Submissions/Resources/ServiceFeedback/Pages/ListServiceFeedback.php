<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages;

use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Models\ServiceFeedback;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

/*
Online feedback arrives from the public form, never from staff. The one
create action here is for PAPER forms: an admin or secretary types in a form
that arrived on paper, so it sits in the database alongside the online ones,
marked as paper (ServiceFeedback::recordPaperForm()).

ListRecords rather than ManageRecords so this stays the only create button -
ManageRecords would add its own. Resource requests have no equivalent: they
come only from the website.
*/
class ListServiceFeedback extends ListRecords
{
    protected static string $resource = ServiceFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add paper form')
                ->icon('heroicon-o-document-plus')
                ->modalHeading('Add a paper feedback form')
                ->modalDescription('Type in a service feedback form that arrived on paper. It is saved alongside online feedback and marked as paper.')
                ->modalSubmitActionLabel('Save paper form')
                ->createAnother(false)
                ->using(fn (array $data): ServiceFeedback => ServiceFeedback::recordPaperForm($data, Filament::auth()->user()))
                ->successNotificationTitle('Paper form saved'),
        ];
    }
}
