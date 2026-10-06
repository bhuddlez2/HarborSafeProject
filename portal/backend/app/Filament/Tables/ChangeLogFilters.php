<?php

namespace App\Filament\Tables;

use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAgent;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
The filters on the Change log (AssessmentEditResource). Built from
AssessmentFilters' helpers, so the card looks and behaves like the assessment
tables': above the table, deferred until Apply, Reset beside it, kept in the
session.

The table lists assessment_edits rows - one per save - and both kinds of
field-level change (assessment_change_log, assessment_answer_change_log) hang
off an edit, so every filter here covers both.

The filters, in card order: changed by and original submitter; date changed
(EditedAt) and agency (admins only, by users.role); victim first and last
name; offender first and last name. Names are matched on the assessment the
edit belongs to, as it is now - never on the logged PreviousValue / NewValue,
which are deliberately not searchable.

Filters only narrow the resource's own AssessmentEdit::visibleTo() query, and
every dropdown's options are scoped the same way, because a dropdown can leak
what the table hides: a police admin is never offered another agency's
officers or the agency itself (Filament_CMS_Design.md section 5, rule 5).
*/
final class ChangeLogFilters
{
    public static function apply(Table $table): Table
    {
        return AssessmentFilters::layout($table, [
            self::changedBy(),
            self::originalSubmitter(),
            AssessmentFilters::dateRange('date_changed', 'EditedAt', 'Changed')->columnStart(1),
            self::agency(),
            ...AssessmentFilters::nameFilters('assessment'),
        ]);
    }

    // Only users who made an edit the user can see.
    private static function changedBy(): SelectFilter
    {
        return SelectFilter::make('changed_by')
            ->label('Changed by')
            ->relationship(
                'editor',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    self::visibleEdits()->select('ChangedBy'),
                ),
            )
            ->searchable()
            ->preload();
    }

    // The officer who submitted the edited assessment. Only submitters of
    // visible assessments that have at least one visible edit.
    private static function originalSubmitter(): SelectFilter
    {
        return SelectFilter::make('submitted_by')
            ->label('Original submitter')
            ->relationship(
                'assessment.submitter',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    self::editedSubmitters(),
                ),
            )
            ->searchable()
            ->preload();
    }

    // Admins only (AssessmentFilters::showsAgency()); hidden from police
    // admins, and a hidden filter is never applied. The agency is the
    // submitting officer's - the same link visibleTo() scopes by.
    private static function agency(): SelectFilter
    {
        return SelectFilter::make('agency')
            ->label('Agency')
            ->relationship(
                'assessment.submitter.lawEnforcementAgent.agency',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    LawEnforcementAgent::query()
                        ->whereIn('user_id', self::editedSubmitters())
                        ->select('agency_id'),
                ),
            )
            ->searchable()
            ->preload()
            ->visible(fn (): bool => AssessmentFilters::showsAgency());
    }

    private static function editedSubmitters(): Builder
    {
        return AssessmentFilters::visibleAssessments()
            ->whereIn('DocumentID', self::visibleEdits()->select('DocumentID'))
            ->select('submitted_by');
    }

    private static function visibleEdits(): Builder
    {
        return AssessmentEdit::query()->visibleTo(Filament::auth()->user());
    }
}
