<?php

namespace App\Filament\Clusters\Logs\Resources\AssessmentEdits\Pages;

use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Clusters\Logs\Resources\AssessmentEdits\AssessmentEditResource;
use Filament\Resources\Pages\ListRecords;

// No header actions: edits are only ever written by AssessmentEditor.
class ListAssessmentEdits extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = AssessmentEditResource::class;
}
