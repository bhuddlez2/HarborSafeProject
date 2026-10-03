<?php

namespace App\Filament\Resources\LawEnforcementAssessments\Pages;

use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLawEnforcementAssessment extends ViewRecord
{
    protected static string $resource = LawEnforcementAssessmentResource::class;

    // Read-only: no Edit or Delete buttons.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
