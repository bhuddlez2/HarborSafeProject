<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages;

use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Models\ServiceFeedback;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

/*
Online feedback arrives from the public form. The one create action here,
"New feedback form", lets an admin or secretary fill a form in themselves -
typically one that arrived on paper - so it sits in the database alongside the
online ones, marked Source = staff (ServiceFeedback::recordStaffEntry()). The
wording deliberately doesn't mention paper: to the person entering it, it is
just a feedback form.

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
                ->label('New feedback form')
                ->icon('heroicon-o-document-plus')
                ->modalHeading('New feedback form')
                ->modalSubmitActionLabel('Save form')
                ->createAnother(false)
                ->using(fn (array $data): ServiceFeedback => ServiceFeedback::recordStaffEntry($data, Filament::auth()->user()))
                ->successNotificationTitle('Feedback form saved'),
        ];
    }
}
