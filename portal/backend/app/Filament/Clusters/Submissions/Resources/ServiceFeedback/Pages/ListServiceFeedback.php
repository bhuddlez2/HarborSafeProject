<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages;

use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use Filament\Resources\Pages\ListRecords;

/*
No create action: submissions arrive from the public form, never from staff.
ListRecords rather than ManageRecords for exactly that reason - ManageRecords
puts a create button on the page by default.
*/
class ListServiceFeedback extends ListRecords
{
    protected static string $resource = ServiceFeedbackResource::class;
}
