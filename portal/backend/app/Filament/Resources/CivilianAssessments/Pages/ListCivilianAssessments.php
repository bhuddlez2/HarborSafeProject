<?php

namespace App\Filament\Resources\CivilianAssessments\Pages;

use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use Filament\Resources\Pages\ListRecords;

/*
ListRecords with no header actions. These arrive from the public civilian flow
only - there is no way for staff to create one, and the officer wizard writes
to law_enforcement_assessment instead. See the resource's class comment.
*/
class ListCivilianAssessments extends ListRecords
{
    protected static string $resource = CivilianAssessmentResource::class;
}
