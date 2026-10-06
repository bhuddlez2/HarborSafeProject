<?php

namespace App\Filament\Tables;

use App\Enums\UserRole;
use App\Models\LawEnforcementAgent;
use App\Models\LawEnforcementAssessment;
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

The filters, in card order: date submitted (both tables); submitting officer
and agency (Assessment review only - agency for admins only, by users.role);
victim first and last name; offender first and last name (both tables). Names
are case-insensitive partial matches with LIKE wildcards taken literally. The
victim's safe phone number is deliberately not filterable.

Layout: an always-open card above the table. Filters are deferred - nothing
changes until Apply - with Reset beside it, and kept in the session so they
survive opening a record and coming back. Apply is Filament's own action: it
renders in the above-content card whenever filters are deferred.
*/
final class AssessmentFilters
{
    public static function apply(Table $table, bool $reviewScreen): Table
    {
        // One row per group - dates; officer and agency; victim; offender -
        // with columnStart(1) opening each row, whichever filters before it
        // the user is shown.
        $filters = [self::dateSubmitted()];

        if ($reviewScreen) {
            $filters[] = self::submittingOfficer()->columnStart(1);
            $filters[] = self::agency();
        }

        array_push(
            $filters,
            self::textContains('victim_first_name', 'VictimFirstName', 'Victim first name')->columnStart(1),
            self::textContains('victim_last_name', 'VictimLastName', 'Victim last name'),
            self::textContains('offender_first_name', 'OffenderFirstName', 'Offender first name')->columnStart(1),
            self::textContains('offender_last_name', 'OffenderLastName', 'Offender last name'),
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

    // Active admins only, by users.role (hasActiveRole) - not the Shield /
    // spatie super_admin role. Police admins never see it, and a hidden filter
    // is never applied. Options are the agencies of officers with a visible
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

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function visibleAssessments(): Builder
    {
        return LawEnforcementAssessment::query()->visibleTo(Filament::auth()->user());
    }
}
