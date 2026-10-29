<?php

namespace App\Filament\Assessments;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;

/*
Every time a law-enforcement assessment's contents open in the panel, one
row goes into assessment_access_log (team plan task 9). The form itself is a
'view', through log() below:

  - View, on My Assessments and Assessment review (viewAction(), shared)
  - Edit, on My Assessments and Assessment review (AssessmentEditForm)

Its history is a 'view_log' ("Viewed Log", 2026-10-28), through logHistory(),
so checking the log doesn't read as opening the form - though it still
shows names and each edit's old and new values, so it is still recorded:

  - the per-row Log / Change log (ChangeHistory::action())
  - an edit row's View on the Logs page (LogResource)

Each calls log() from a hook Filament runs once, when the pop-up mounts:
after the record is found through the table's scoped query
(LawEnforcementAssessment::visibleTo()) and the action is authorized, and
before the modal is sent to the browser. So a refused open logs nothing,
re-renders inside an open pop-up log nothing more, and a failed write stops
the pop-up opening (fail closed, like every other row in that table).

ViewAction and EditAction fill their schema through mountUsing, so they log in
afterFormFilled. A pop-up with no schema (the per-row Change log) never calls
afterFormFilled; it logs in mountUsing instead.

PDF downloads are logged separately, by AssessmentPdfController.
*/
final class AssessmentViews
{
    // The View pop-up of both law-enforcement assessment tables.
    public static function viewAction(): ViewAction
    {
        return ViewAction::make()
            ->modalHeading('View Assessment')
            ->afterFormFilled(fn (LawEnforcementAssessment $record) => self::log($record))
            ->extraModalFooterActions(fn (LawEnforcementAssessment $record): array => [
                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->url(route('staff.assessments.pdf', $record->DocumentID))
                    ->openUrlInNewTab(),
            ]);
    }

    // One 'view' row for the signed-in user. No catch: if the row can't be
    // written, the exception stops the pop-up opening.
    public static function log(LawEnforcementAssessment $record): void
    {
        AssessmentAccessLog::record($record, AssessmentAccess::View, Filament::auth()->user());
    }

    // One 'view_log' row: the record's history was opened. Fails closed, as
    // log() does.
    public static function logHistory(LawEnforcementAssessment $record): void
    {
        AssessmentAccessLog::record($record, AssessmentAccess::ViewLog, Filament::auth()->user());
    }
}
