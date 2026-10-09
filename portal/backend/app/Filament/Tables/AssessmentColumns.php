<?php

namespace App\Filament\Tables;

use App\Filament\Assessments\ChangeHistory;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use App\Support\Timezones;
use Filament\Tables\Columns\TextColumn;

/*
The one column list for every law-enforcement assessment table - My
Assessments (LawEnforcementAssessmentResource) and Assessment review
(AssessmentReviewResource) - so the two cannot drift apart, the same way
AssessmentFilters keeps their filters in step. Any future assessment table
must use it too.

In order:

  Submitted · Officer (Assessment review only) · Victim · Offender ·
  Relationship · Yes · Status

Officer only on the review screen: on My Assessments every row is the
signed-in officer's own. The row buttons (Change log, View, and Edit on My
Assessments only) are each resource's recordActions(), after these.

Both tables' queries must eager-load assessmentAnswers and submitter and
->withCount('edits') (Status reads edits_count).
*/
final class AssessmentColumns
{
    /** @return list<TextColumn> */
    public static function for(bool $reviewScreen): array
    {
        return array_values(array_filter([
            TextColumn::make('DateCreated')
                ->label('Submitted')
                ->dateTime('M j, Y g:i a')
                ->timezone(Timezones::DISPLAY)
                ->alignCenter()
                ->sortable(),

            $reviewScreen
                ? TextColumn::make('submitter.name')
                    ->label('Officer')
                    ->alignCenter()
                    ->placeholder('Unknown')
                    ->sortable()
                : null,

            TextColumn::make('VictimLastName')
                ->label('Victim')
                ->alignCenter()
                ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                    ($record->VictimFirstName ?? '').' '.($state ?? ''),
                ) ?: '-')
                ->searchable(['VictimFirstName', 'VictimLastName']),

            TextColumn::make('OffenderLastName')
                ->label('Offender')
                ->alignCenter()
                ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                    ($record->OffenderFirstName ?? '').' '.($state ?? ''),
                ) ?: '-')
                ->searchable(['OffenderFirstName', 'OffenderLastName']),

            // Its own column rather than a grey line under the offender.
            TextColumn::make('OffenderVictimRelationship')
                ->label('Relationship')
                ->alignCenter()
                ->placeholder('-'),

            // The screening result the eleven answers add up to. Counted in
            // PHP rather than SQL so the column list stays readable; the
            // relation is eager-loaded by each table's query.
            TextColumn::make('risk_count')
                ->label('Yes')
                ->badge()
                ->alignCenter()
                ->state(fn (LawEnforcementAssessment $record): string => self::yesCount($record).' of 11')
                ->color(fn (LawEnforcementAssessment $record): string => match (true) {
                    self::yesCount($record) >= 4 => 'danger',
                    self::yesCount($record) >= 1 => 'warning',
                    default => 'gray',
                }),

            ChangeHistory::amendedColumn(),
        ]));
    }

    public static function yesCount(LawEnforcementAssessment $record): int
    {
        $answers = $record->assessmentAnswers;

        if (! $answers) {
            return 0;
        }

        return collect(array_keys(NewAssessment::QUESTIONS))
            ->filter(fn (int $id): bool => (bool) $answers->{"RiskIndicator{$id}"})
            ->count();
    }
}
