<?php

namespace App\Filament\Tables;

use App\Models\LawEnforcementAgent;
use App\Models\LogEntry;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
The filters on the Logs page (LogResource), behind its Filters button. Built
from AssessmentFilters' helpers, so the card looks and behaves like the
assessment tables': deferred until Apply, Reset beside it, kept in the
session.

The table mixes views, PDF downloads and edits (App\Models\LogEntry). Two
filters apply to every row:
  - Who: the person who viewed, downloaded or edited;
  - Date: the row's own time (OccurredAt).
The rest look at the officer assessment a row is about, so setting any of
them leaves only officer-assessment rows (civilian assessment rows have no
officer assessment to match):
  - Original submitter, Assessment ID (part of its DocumentID - the 8
    characters in a PDF's file name work), Agency (admins and super admins
    only, by users.role), victim and offender first and last name.
Names are matched on the assessment as it is now - never on an edit's logged
PreviousValue / NewValue, which are deliberately not searchable.

Filters only narrow the page's own LogEntry::visibleTo() rows, and every
dropdown's options come from the same scoped rows, because a dropdown can leak
what the table hides: a police admin is never offered another agency's people
or the agency itself (Filament_CMS_Design.md section 5, rule 5).
*/
final class ChangeLogFilters
{
    public static function apply(Table $table): Table
    {
        return AssessmentFilters::layout($table, [
            self::who(),
            self::originalSubmitter(),
            AssessmentFilters::textContains('assessment_id', 'DocumentID', 'Assessment ID', 'lawEnforcementAssessment'),
            AssessmentFilters::dateRange('date', 'OccurredAt', 'Date')->columnStart(1),
            self::agency(),
            ...AssessmentFilters::nameFilters('lawEnforcementAssessment'),
        ]);
    }

    // Only people who appear in a row the user can see.
    private static function who(): SelectFilter
    {
        return SelectFilter::make('who')
            ->label('Who')
            ->relationship(
                'user',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    self::visibleRows()->select('UserID'),
                ),
            )
            ->searchable()
            ->preload();
    }

    // The officer who submitted the assessment a row is about. Only
    // submitters of visible assessments that appear in a visible row.
    private static function originalSubmitter(): SelectFilter
    {
        return SelectFilter::make('submitted_by')
            ->label('Original submitter')
            ->relationship(
                'lawEnforcementAssessment.submitter',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    self::loggedSubmitters(),
                ),
            )
            ->searchable()
            ->preload();
    }

    // Admins and super admins only (AssessmentFilters::showsAgency()); hidden
    // from police admins, and a hidden filter is never applied. The agency is
    // the submitting officer's - the same link visibleTo() scopes by.
    private static function agency(): SelectFilter
    {
        return SelectFilter::make('agency')
            ->label('Agency')
            ->relationship(
                'lawEnforcementAssessment.submitter.lawEnforcementAgent.agency',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    LawEnforcementAgent::query()
                        ->whereIn('user_id', self::loggedSubmitters())
                        ->select('agency_id'),
                ),
            )
            ->searchable()
            ->preload()
            ->visible(fn (): bool => AssessmentFilters::showsAgency());
    }

    private static function loggedSubmitters(): Builder
    {
        return AssessmentFilters::visibleAssessments()
            ->whereIn(
                'DocumentID',
                self::visibleRows()->whereNotNull('LawEnforcementDocumentID')->select('LawEnforcementDocumentID'),
            )
            ->select('submitted_by');
    }

    private static function visibleRows(): Builder
    {
        return LogEntry::visibleTo(Filament::auth()->user());
    }
}
