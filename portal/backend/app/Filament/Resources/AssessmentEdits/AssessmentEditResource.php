<?php

namespace App\Filament\Resources\AssessmentEdits;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/*
The change log: every edit to a law-enforcement assessment, one row per save,
for admins (all of them) and police admins (their own agency's). Officers read
the history of their own records inside My Assessments instead.

Read-only and append-only. Rows are written by App\Services\AssessmentEditor
and the model refuses updates and deletes, so there is no create, edit or
delete here - only a View pop-up showing the same per-edit card the record's
own Change history section uses.

Who sees which edits is LawEnforcementAssessment::visibleTo(), the same rule as
the assessments themselves - never a separate one.
*/
class AssessmentEditResource extends Resource
{
    protected static ?string $model = AssessmentEdit::class;

    protected static ?string $slug = 'assessment-changes';

    protected static ?string $navigationLabel = 'Change log';

    protected static ?string $modelLabel = 'edit';

    protected static ?string $pluralModelLabel = 'Change log';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    protected static ?int $navigationSort = 3;

    // A police admin finds this in their single "Police Admin" group;
    // everyone else keeps the group and position above. Grouping and order
    // only - see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::CHANGE_LOG : parent::getNavigationSort();
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::PoliceAdmin,
        );
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess()
            && static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // Edits to assessments the user may see, and nothing else. Also scopes
    // the record lookup behind the View action.
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()->whereHas(
            'assessment',
            fn (Builder $assessment): Builder => $assessment->visibleTo($user),
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assessment')
                    ->schema([
                        TextEntry::make('assessment.VictimLastName')
                            ->label('Victim')
                            ->formatStateUsing(fn (?string $state, AssessmentEdit $record): string => self::personName($record->assessment, 'Victim')),
                        TextEntry::make('assessment.OffenderLastName')
                            ->label('Offender')
                            ->formatStateUsing(fn (?string $state, AssessmentEdit $record): string => self::personName($record->assessment, 'Offender')),
                        TextEntry::make('assessment.DateCreated')
                            ->label('Submitted')
                            ->dateTime('M j, Y g:i a'),
                    ])
                    ->columns(3),

                Section::make('This edit')
                    ->schema([ChangeHistory::entry()]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('EditedAt')
                    ->label('When')
                    ->dateTime('M j, Y g:i a')
                    ->sortable(),

                TextColumn::make('editor.name')
                    ->label('Officer')
                    ->placeholder('Unknown')
                    ->searchable(),

                TextColumn::make('assessment.VictimLastName')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state, AssessmentEdit $record): string => self::personName($record->assessment, 'Victim')
                        .' / '.self::personName($record->assessment, 'Offender'))
                    ->description('Victim / offender'),

                TextColumn::make('Reason')
                    ->limit(60)
                    ->tooltip(fn (AssessmentEdit $record): string => $record->Reason)
                    ->wrap(),

                TextColumn::make('changes_count')
                    ->label('Changes')
                    ->state(fn (AssessmentEdit $record): int => (int) $record->detail_changes_count + (int) $record->answer_changes_count)
                    ->formatStateUsing(fn (int $state): string => $state.' '.str('field')->plural($state))
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('EditedAt', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['editor', 'assessment'])
                ->withCount(['detailChanges', 'answerChanges']))
            ->filters([
                // Only editors of edits the user can already see, so a police
                // admin is never offered another agency's officer names.
                SelectFilter::make('ChangedBy')
                    ->label('Officer')
                    ->relationship(
                        'editor',
                        'name',
                        fn (Builder $query): Builder => $query->whereIn(
                            $query->qualifyColumn('id'),
                            static::getEloquentQuery()->select('ChangedBy'),
                        ),
                    )
                    ->searchable(),

                Filter::make('EditedAt')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('EditedAt', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('EditedAt', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No edits yet')
            ->emptyStateDescription('Edits officers make to their submitted assessments appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessmentEdits::route('/'),
        ];
    }

    private static function personName(?LawEnforcementAssessment $assessment, string $who): string
    {
        return trim(($assessment?->{$who.'FirstName'} ?? '').' '.($assessment?->{$who.'LastName'} ?? '')) ?: '-';
    }
}
