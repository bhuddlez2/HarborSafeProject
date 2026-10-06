<?php

namespace App\Filament\Resources\AssessmentEdits\Pages;

use App\Filament\Resources\AssessmentEdits\AssessmentEditResource;
use Filament\Resources\Pages\ListRecords;

// No header actions: edits are only ever written by AssessmentEditor.
class ListAssessmentEdits extends ListRecords
{
    protected static string $resource = AssessmentEditResource::class;
}
