<?php

namespace App\Filament\Tables;

use App\Enums\OffenderRelationship;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Support\HighRisk;
use App\Support\Timezones;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

Search by field (#64), on the two law-enforcement tables (for()): Filament's
own per-column search, a box under a column's heading for what that column
shows -

  Victim     the victim's name: "First Last", or either part
  Offender   the offender's name the same way, or the relationship's label
             ("Spouse", "Other: <detail>") shown under it
  Submitted  the submitting officer's name (Assessment review only, where
             "by {officer}" shows under the date)

Names and relationship are encrypted, so they are searched like every other
encrypted search (EncryptedColumnSearch, App\Support\EncryptedSearch): the
viewer's own visibleTo() rows are decrypted in PHP and the table narrowed to
the matches. Case-insensitive, % and _ literal. The main search bar shares
these searches. The safe phone number is never searchable. Civilian
assessments keeps its main bar only, searching each name column as before.
*/
final class AssessmentColumns
{
    /** The law-enforcement tables. @return list<TextColumn> */
    public static function for(bool $reviewScreen): array
    {
        return self::columns(LawEnforcementAssessment::class, withOfficer: $reviewScreen, fieldSearch: true);
    }

    /** Civilian assessments. @return list<TextColumn> */
    public static function civilian(): array
    {
        return self::columns(PrivateAssessment::class, withOfficer: false, fieldSearch: false);
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

    // A person's name as typed in a search box: first, last or "First Last";
    // with $withRelationship (the offender) also the relationship's label.
    private static function nameSearch(string $model, string $person, bool $withRelationship = false): Closure
    {
        $first = "{$person}FirstName";
        $last = "{$person}LastName";
        $relationship = $withRelationship ? ['OffenderVictimRelationship', 'OffenderVictimRelationshipOther'] : [];

        return EncryptedColumnSearch::matching($model, [$first, $last, ...$relationship], fn ($row): array => [
            $row->{$first},
            $row->{$last},
            trim(($row->{$first} ?? '').' '.($row->{$last} ?? '')),
            ...($withRelationship ? [OffenderRelationship::display($row->OffenderVictimRelationship, $row->OffenderVictimRelationshipOther)] : []),
        ]);
    }

    /**
     * @param  class-string<LawEnforcementAssessment|PrivateAssessment>  $model
     * @return list<TextColumn>
     */
    private static function columns(string $model, bool $withOfficer, bool $fieldSearch): array
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
            $submitted->description(fn (LawEnforcementAssessment $record): string => 'by '.($record->submitter?->name ?? 'Unknown'))
                // users.name is plain text ("First Last"), so the database searches it.
                ->searchable(
                    query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'submitter',
                        fn (Builder $users): Builder => $users->where(
                            'name',
                            'like',
                            '%'.AssessmentFilters::escapeLike(mb_strtolower(preg_replace('/\s+/u', ' ', trim($search)))).'%',
                        ),
                    ),
                    isIndividual: $fieldSearch,
                )
                ->splitIndividualSearchTerms(false);
        }

        return [
            $submitted,

            RecordCards::title(TextColumn::make('VictimLastName')
                ->label('Victim')
                ->formatStateUsing($fullName('VictimFirstName'))
                ->searchable(query: $fieldSearch
                    ? self::nameSearch($model, 'Victim')
                    : EncryptedColumnSearch::query($model, ['VictimFirstName', 'VictimLastName']), isIndividual: $fieldSearch)
                ->splitIndividualSearchTerms(false)),

            TextColumn::make('OffenderLastName')
                ->label('Offender')
                ->formatStateUsing($fullName('OffenderFirstName'))
                ->description(fn ($record): ?string => OffenderRelationship::display(
                    $record->OffenderVictimRelationship,
                    $record->OffenderVictimRelationshipOther,
                ))
                ->searchable(query: $fieldSearch
                    ? self::nameSearch($model, 'Offender', withRelationship: true)
                    : EncryptedColumnSearch::query($model, ['OffenderFirstName', 'OffenderLastName']), isIndividual: $fieldSearch)
                ->splitIndividualSearchTerms(false),

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
