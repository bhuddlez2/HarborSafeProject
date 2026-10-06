<?php

namespace App\Filament\Tables;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
use App\Support\AssessmentFields;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
The one filter setup for every law-enforcement assessment table: My Assessments
(LawEnforcementAssessmentResource) and Assessment review
(AssessmentReviewResource) both call apply() from their table(), so the two
cannot drift apart. Any future assessment table must use it too.

Filters only ever narrow a screen's own query, which each resource already
scopes with LawEnforcementAssessment::visibleTo(). The dropdown options are
scoped by the same rule, because a dropdown can leak what the table hides: a
police admin must never be offered another agency's officers or the agency
itself (Filament_CMS_Design.md section 5, rule 5).

The filters, in card order: date submitted; submitting officer and (admin
only) agency on Assessment review; victim first name, last name and sex;
offender first name, last name and sex; relationship to victim; and "Answered
Yes to", the eleven questions, every selected one answered Yes. Names and
relationship are case-insensitive partial matches with LIKE wildcards taken
literally. Sex takes the stored code or its word (m / male). The victim's safe
phone number is deliberately not filterable.

The sex and relationship filters are typed text for now, until the team fixes
their allowed values (Filament_CMS_Design.md section 12) - then they become
dropdowns.

Layout: an always-open card above the table. Filters are deferred - nothing
changes until Apply - with Reset beside it, and kept in the session so they
survive opening a record and coming back. Apply is Filament's own action: it
renders in the above-content card whenever filters are deferred.
*/
final class AssessmentFilters
{
    public static function apply(Table $table, bool $reviewScreen): Table
    {
        $filters = [self::dateSubmitted()];

        if ($reviewScreen) {
            $filters[] = self::submittingOfficer();
            $filters[] = self::agency();
        }

        // columnStart(1) opens a new row for each group, whichever of the
        // filters above it the user is shown.
        array_push(
            $filters,
            self::textContains('victim_first_name', 'VictimFirstName', 'Victim first name')->columnStart(1),
            self::textContains('victim_last_name', 'VictimLastName', 'Victim last name'),
            self::sexIs('victim_sex', 'VictimSex', 'Victim sex'),
            self::textContains('offender_first_name', 'OffenderFirstName', 'Offender first name')->columnStart(1),
            self::textContains('offender_last_name', 'OffenderLastName', 'Offender last name'),
            self::sexIs('offender_sex', 'OffenderSex', 'Offender sex'),
            self::textContains('relationship', 'OffenderVictimRelationship', 'Relationship to victim')->columnStart(1),
            self::answeredYes(),
        );

        return $table
            ->filters($filters)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters()
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->persistFiltersInSession();
    }

    private static function dateSubmitted(): Filter
    {
        return Filter::make('date_submitted')
            ->schema([
                DatePicker::make('from')->label('Submitted from'),
                DatePicker::make('until')->label('Submitted until'),
            ])
            ->columns(2)
            ->columnSpan(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('DateCreated', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('DateCreated', '<=', $date)))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make('Submitted from '.$data['from'])->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make('Submitted until '.$data['until'])->removeField('until');
                }

                return $indicators;
            });
    }

    // Only officers whose submissions the user can see, so a police admin is
    // never offered another agency's officers (and nobody is offered admins or
    // secretaries).
    private static function submittingOfficer(): SelectFilter
    {
        return SelectFilter::make('submitted_by')
            ->label('Submitting officer')
            ->relationship(
                'submitter',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    self::visibleAssessments()->select('submitted_by'),
                ),
            )
            ->searchable()
            ->preload();
    }

    // Admin only. Options are the agencies of officers with a visible
    // submission; the same closure also scopes the filter's own whereHas.
    private static function agency(): SelectFilter
    {
        return SelectFilter::make('agency')
            ->label('Agency')
            ->relationship(
                'submitter.lawEnforcementAgent.agency',
                'name',
                fn (Builder $query): Builder => $query->whereIn(
                    $query->qualifyColumn('id'),
                    LawEnforcementAgent::query()
                        ->whereIn('user_id', self::visibleAssessments()->select('submitted_by'))
                        ->select('agency_id'),
                ),
            )
            ->searchable()
            ->preload()
            ->visible(fn (): bool => (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin));
    }

    // Case-insensitive (the columns use a _ci collation) partial match, with
    // the user's % and _ escaped so they match literally. Blank is off.
    private static function textContains(string $name, string $column, string $label): Filter
    {
        return Filter::make($name)
            ->schema([TextInput::make('value')->label($label)->maxLength(50)])
            ->query(function (Builder $query, array $data) use ($column): void {
                $value = trim((string) ($data['value'] ?? ''));

                if ($value !== '') {
                    $query->where($column, 'like', '%'.self::escapeLike($value).'%');
                }
            })
            ->indicateUsing(fn (array $data): array => filled(trim((string) ($data['value'] ?? '')))
                ? [Indicator::make($label.': '.trim($data['value']))->removeField('value')]
                : []);
    }

    // The stored code (M / F / O) or its word, any case. Anything else
    // matches nothing rather than erroring. Blank is off.
    private static function sexIs(string $name, string $column, string $label): Filter
    {
        return Filter::make($name)
            ->schema([
                TextInput::make('value')
                    ->label($label)
                    ->placeholder('M, F, O or male / female / other')
                    ->maxLength(10),
            ])
            ->query(function (Builder $query, array $data) use ($column): void {
                $typed = trim((string) ($data['value'] ?? ''));

                if ($typed === '') {
                    return;
                }

                $code = self::sexCode($typed);

                if ($code === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where($column, $code);
            })
            ->indicateUsing(function (array $data) use ($label): array {
                $typed = trim((string) ($data['value'] ?? ''));

                if ($typed === '') {
                    return [];
                }

                $code = self::sexCode($typed);
                $shown = $code === null ? "{$typed} (no match)" : AssessmentFields::SEX_OPTIONS[$code];

                return [Indicator::make("{$label}: {$shown}")->removeField('value')];
            });
    }

    // Rows where every selected question was answered Yes. Raw answers only -
    // no score or danger summary.
    private static function answeredYes(): SelectFilter
    {
        return SelectFilter::make('answered_yes')
            ->label('Answered Yes to')
            ->multiple()
            ->searchable()
            ->options(collect(NewAssessment::QUESTIONS)
                ->mapWithKeys(fn (string $question, int $id): array => [$id => "{$id}. {$question}"])
                ->all())
            ->columnSpanFull()
            ->query(function (Builder $query, array $data): void {
                $ids = self::questionIds($data['values'] ?? []);

                if ($ids === []) {
                    return;
                }

                $query->whereHas('assessmentAnswers', function (Builder $answers) use ($ids): void {
                    foreach ($ids as $id) {
                        $answers->where("RiskIndicator{$id}", true);
                    }
                });
            })
            ->indicateUsing(function (array $data): array {
                $ids = self::questionIds($data['values'] ?? []);

                return $ids === []
                    ? []
                    : [Indicator::make('Answered Yes to: '.collect($ids)->map(fn (int $id): string => "Q{$id}")->implode(', '))];
            });
    }

    // Only real question numbers survive, so the filter state can never put
    // anything but RiskIndicator1-11 into a column name.
    private static function questionIds(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn (int | string $value): int => (int) $value)
            ->filter(fn (int $id): bool => array_key_exists($id, NewAssessment::QUESTIONS))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function sexCode(string $typed): ?string
    {
        $typed = mb_strtolower(trim($typed));

        foreach (AssessmentFields::SEX_OPTIONS as $code => $word) {
            if ($typed === mb_strtolower($code) || $typed === mb_strtolower($word)) {
                return $code;
            }
        }

        return null;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function visibleAssessments(): Builder
    {
        return LawEnforcementAssessment::query()->visibleTo(Filament::auth()->user());
    }
}
