<?php

namespace App\Filament\Resources\AssessmentReview\Pages;

use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use Filament\Resources\Pages\ListRecords;

/*
ListRecords with no header actions. Assessments are only ever created by the
officer wizard (App\Filament\Pages\NewAssessment), so there is deliberately no
create button here - see the resource's class comment.
*/
class ListAssessmentReview extends ListRecords
{
    protected static string $resource = AssessmentReviewResource::class;
}
