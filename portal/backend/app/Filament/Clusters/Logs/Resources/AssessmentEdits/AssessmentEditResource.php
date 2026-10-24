<?php

namespace App\Filament\Clusters\Logs\Resources\AssessmentEdits;

use App\Filament\Assessments\AssessmentViews;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Clusters\Logs\LogsCluster;
use App\Filament\Clusters\Logs\Resources\AssessmentEdits\Pages\ListAssessmentEdits;
use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Filament\Tables\ChangeLogFilters;
use App\Filament\Tables\TableAlignment;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Support\Timezones;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
The change log, the second tab of Logs (/logs/changes,
App\Filament\Clusters\Logs): every edit to a law-enforcement assessment, one
row per save, for admins (all of them) and police admins with an agency (their
own agency's). Officers read the history of their own records inside My
Assessments instead.

Read-only and append-only. Rows are written by App\Services\AssessmentEditor
and the model refuses updates and deletes, so there is no create, edit or
delete here - only a View pop-up showing the same per-edit card the record's
own Change history section uses.

Who sees which edits is LawEnforcementAssessment::visibleTo(), the same rule as
the assessments themselves - never a separate one.
*/
class AssessmentEditResource extends Resource
{
    use AllowsOnlyListedActions;

    protected static ?string $model = AssessmentEdit::class;

    // The second tab of Logs: /logs/changes.
    protected static ?string $cluster = LogsCluster::class;

    protected static ?string $slug = 'changes';

    protected static ?string $navigationLabel = 'Change log';

    protected static ?string $modelLabel = 'edit';

    protected static ?string $pluralModelLabel = 'Change log';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    // Tab order inside the cluster: Access log, then Change log.
    protected static ?int $navigationSort = 2;

    // Active admins, and active police admins with an agency - the Logs
    // cluster's rule, shared by both tabs. A police admin with no agency
    // would see no rows anyway; they are refused outright, like the Access log.
    public static function canAccess(): bool
    {
        return LogsCluster::canOpenLogs();
    }

    // The list and its View pop-up. Rows are append-only.
    protected static function allowedActions(): array
    {
        return ['view'];
    }

    // Only an edit to an assessment the user may see.
    protected static function allowsRecord(string $action, Model $record): bool
    {
        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    // Edits to assessments the user may see, and nothing else. Also scopes
    // the record lookup behind the View action.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Filament::auth()->user());
    }

    public static function infolist(Schema $schema): Schema
    {
        // The same design as each record's Change log pop-up: a header naming
        // the assessment, then this one edit.
        return $schema
            ->components([
                ChangeHistory::entry(),
            ]);
    }

    // Filters: App\Filament\Tables\ChangeLogFilters.
    public static function table(Table $table): Table
    {
        return ChangeLogFilters::apply($table
            ->columns([
                TextColumn::make('EditedAt')
                    ->label('When')
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Timezones::DISPLAY)
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

                TableAlignment::symbol(TextColumn::make('changes_count')
                    ->label('Changes')
                    ->state(fn (AssessmentEdit $record): int => (int) $record->detail_changes_count + (int) $record->answer_changes_count)
                    ->formatStateUsing(fn (int $state): string => $state.' '.str('field')->plural($state))
                    ->badge()
                    ->color('gray')),
            ])
            ->defaultSort('EditedAt', 'desc')
            // Cards instead of a wide table on phones (team plan task 10).
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['editor', 'assessment'])
                ->withCount(['detailChanges', 'answerChanges']))
            ->recordActions([
                // Shows the edit's old and new values, so opening it is a view
                // of the assessment - see App\Filament\Assessments\AssessmentViews.
                ViewAction::make()
                    ->afterFormFilled(fn (AssessmentEdit $record) => AssessmentViews::log($record->assessment)),
            ])
            ->emptyStateHeading('No edits yet')
            ->emptyStateDescription('Edits officers make to their submitted assessments appear here.'));
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
