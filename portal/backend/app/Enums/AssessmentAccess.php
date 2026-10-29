<?php

namespace App\Enums;

use App\Models\User;

// What someone did with an assessment, as recorded in assessment_access_log
// (App\Models\AssessmentAccessLog): opened its View pop-up, or downloaded it
// as a PDF. Both put the victim's safe phone number in front of someone.
//
// DownloadWithLog (Burchfield B43, 2026-10-28) is the follow-up page's
// "Download PDF with log": the form plus its follow-up Log. Officers and
// police admins never see follow-up information, so to anyone but admins and
// super admins it is shown as a plain download (shownTo()).
//
// ViewLog (2026-10-28) is opening a law-enforcement assessment's history: the
// Log pop-up on Assessment review, the Change log pop-up on My Assessments,
// or an edit row's View on the Logs page. It is its own kind so that checking
// the history doesn't read as opening the form. The Follow-ups queue's Log
// still records a plain View: that log is follow-up information, which police
// admins never see.
enum AssessmentAccess: string
{
    case View = 'view';
    case ViewLog = 'view_log';
    case Download = 'download';
    case DownloadWithLog = 'download_with_log';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Viewed',
            self::ViewLog => 'Viewed Log',
            self::Download => 'Downloaded PDF',
            self::DownloadWithLog => 'Downloaded PDF with log',
        };
    }

    // How this access reads to $viewer: a download with the follow-up log is
    // a plain download to anyone who doesn't work follow-ups on assessments.
    public function shownTo(?User $viewer): self
    {
        return $this === self::DownloadWithLog && ! self::seesFollowUpExports($viewer) ? self::Download : $this;
    }

    public static function seesFollowUpExports(?User $viewer): bool
    {
        return (bool) $viewer?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin);
    }
}
