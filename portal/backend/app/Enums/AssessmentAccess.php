<?php

namespace App\Enums;

// What someone did with an assessment, as recorded in assessment_access_log
// (App\Models\AssessmentAccessLog): opened its View pop-up, or downloaded it
// as a PDF. Both put the victim's safe phone number in front of someone.
enum AssessmentAccess: string
{
    case View = 'view';
    case Download = 'download';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Viewed',
            self::Download => 'Downloaded PDF',
        };
    }
}
