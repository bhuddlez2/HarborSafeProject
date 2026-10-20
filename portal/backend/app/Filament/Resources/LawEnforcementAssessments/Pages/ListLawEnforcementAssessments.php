<?php

namespace App\Filament\Resources\LawEnforcementAssessments\Pages;

use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use Filament\Resources\Pages\ListRecords;

class ListLawEnforcementAssessments extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = LawEnforcementAssessmentResource::class;

    // Read-only: no Create button.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
