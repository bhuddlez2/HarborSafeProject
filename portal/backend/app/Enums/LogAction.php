<?php

namespace App\Enums;

// What a row on the Logs page records (App\Models\LogEntry): a view or PDF
// download from assessment_access_log, or an edit from assessment_edits. The
// one list of actions: the page's Show dropdown, its Action badge and the
// model's cast all read it. The access actions keep their AssessmentAccess
// values and labels, so the two enums can't drift apart.
enum LogAction: string
{
    case View = 'view';
    case Download = 'download';
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::View => AssessmentAccess::View->label(),
            self::Download => AssessmentAccess::Download->label(),
            self::Edit => 'Edited',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::View => 'gray',
            self::Download => 'warning',
            self::Edit => 'info',
        };
    }
}
