<?php

namespace App\Enums;

use App\Models\User;

// What a row on the Logs page records (App\Models\LogEntry): a view, log
// view or PDF download from assessment_access_log, or an edit from assessment_edits. The
// one list of actions: the page's Show dropdown, its Action badge and the
// model's cast all read it. The access actions keep their AssessmentAccess
// values and labels, so the two enums can't drift apart.
enum LogAction: string
{
    case View = 'view';
    case ViewLog = 'view_log';
    case Download = 'download';
    case DownloadWithLog = 'download_with_log';
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::View => AssessmentAccess::View->label(),
            self::ViewLog => AssessmentAccess::ViewLog->label(),
            self::Download => AssessmentAccess::Download->label(),
            self::DownloadWithLog => AssessmentAccess::DownloadWithLog->label(),
            self::Edit => 'Edited',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::View => 'gray',
            self::ViewLog => 'primary',
            self::Download, self::DownloadWithLog => 'warning',
            self::Edit => 'info',
        };
    }

    // As AssessmentAccess::shownTo(): a police admin sees a download with
    // the follow-up log as a plain download.
    public function shownTo(?User $viewer): self
    {
        return $this === self::DownloadWithLog && ! AssessmentAccess::seesFollowUpExports($viewer) ? self::Download : $this;
    }

    // The Show dropdown's choices for $viewer: no separate "with log" choice
    // for someone who sees those downloads as plain ones.
    public static function shownCases(?User $viewer): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $action): bool => $action->shownTo($viewer) === $action,
        ));
    }

    // The stored values a Show choice matches for $viewer: "Downloaded PDF"
    // also finds the downloads with the log that they see as plain ones.
    public function matchedValues(?User $viewer): array
    {
        return $this === self::Download && ! AssessmentAccess::seesFollowUpExports($viewer)
            ? [self::Download->value, self::DownloadWithLog->value]
            : [$this->value];
    }
}
