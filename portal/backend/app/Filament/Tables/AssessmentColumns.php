<?php

namespace App\Filament\Tables;

use App\Enums\OffenderRelationship;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Support\HighRisk;
use App\Support\Timezones;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/*
The one column list for every assessment table - My Assessments
(LawEnforcementAssessmentResource), Assessment review
(AssessmentReviewResource) and Civilian assessments
(CivilianAssessmentResource) - so they cannot drift apart, the same way
AssessmentFilters keeps their filters in step. Any future assessment table
must use it too.

Four columns, kept few so the tables read easily (Finishing Touches item 9):

  Submitted · Victim · Offender · Yes

with the officer's name in grey under the date (Assessment review only: on
My Assessments every row is the signed-in officer's own, and civilian
submissions have no officer) and the relationship in grey under the
offender. There is no Status (Amended) column: the Change log button already
shows it, purple when the record has been edited and gray when it hasn't.
The row buttons are each resource's recordActions(), after these.

cards() turns each row into a card on a phone and stacks the buttons on
narrower screens (RecordCards): victim and Yes badge on top, then offender,
then submitted, then the buttons. On a table the buttons are centred in
their column (TableAlignment::buttons()).

The law-enforcement tables' queries must eager-load assessmentAnswers and
submitter and ->withCount('edits') (the Change log button reads
edits_count); the civilian one assessmentAnswers.

No search bar: these tables search only through their filter card
(AssessmentFilters - dates, victim/offender names, and on Assessment review
the officer and, for admins, the agency), so no column here is searchable.
*/
final class AssessmentColumns
{
    /** The law-enforcement tables. @return list<TextColumn> */
    public static function for(bool $reviewScreen): array
    {
        return self::columns(withOfficer: $reviewScreen);
    }

    /** Civilian assessments. @return list<TextColumn> */
    public static function civilian(): array
    {
        return self::columns(withOfficer: false);
    }

    public static function cards(Table $table): Table
    {
        return TableAlignment::buttons(RecordCards::apply($table));
    }

    public static function yesCount(LawEnforcementAssessment|PrivateAssessment $record): int
    {
        $answers = $record->assessmentAnswers;

        if (! $answers) {
            return 0;
        }

        return collect(array_keys(NewAssessment::QUESTIONS))
            ->filter(fn (int $id): bool => (bool) $answers->{"RiskIndicator{$id}"})
            ->count();
    }

    /** @return list<TextColumn> */
    private static function columns(bool $withOfficer): array
    {
        $fullName = fn (string $first) => fn (?string $state, $record): string => trim(
            ($record->{$first} ?? '').' '.($state ?? ''),
        ) ?: '-';

        $submitted = RecordCards::meta(TextColumn::make('DateCreated'))
            ->label('Submitted')
            ->dateTime('M j, Y g:i a')
            ->timezone(Timezones::DISPLAY)
            ->sortable();

        if ($withOfficer) {
            $submitted->description(fn (LawEnforcementAssessment $record): string => 'by '.($record->submitter?->name ?? 'Unknown'));
        }

        return [
            $submitted,

            RecordCards::title(TextColumn::make('VictimLastName')
                ->label('Victim')
                ->formatStateUsing($fullName('VictimFirstName'))),

            // Built from the record, not formatted: a civilian offender's last
            // name may be missing (B29), and Filament skips formatting a null state.
            TextColumn::make('OffenderLastName')
                ->label('Offender')
                ->state(fn ($record): string => $fullName('OffenderFirstName')($record->OffenderLastName, $record))
                ->description(fn ($record): ?string => OffenderRelationship::display(
                    $record->OffenderVictimRelationship,
                    $record->OffenderVictimRelationshipOther,
                )),

            // The screening result the eleven answers add up to: red when
            // high risk (HighRisk, the rule Analytics counts), amber for any
            // other yes. Counted in PHP rather than SQL so the column list
            // stays readable; the relation is eager-loaded by each table's query.
            RecordCards::corner(TableAlignment::symbol(TextColumn::make('risk_count')
                ->label('Yes')
                ->badge()
                ->state(fn ($record): string => self::yesCount($record).' of 11')
                ->color(fn ($record): string => match (true) {
                    HighRisk::of($record->assessmentAnswers) => 'danger',
                    self::yesCount($record) >= 1 => 'warning',
                    default => 'gray',
                }))),
        ];
    }
}
