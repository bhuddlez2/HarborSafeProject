<?php

namespace App\Filament\Resources\Logs;

use App\Enums\LogAction;
use App\Enums\UserRole;
use App\Filament\Assessments\AssessmentViews;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Filament\Tables\ChangeLogFilters;
use App\Filament\Tables\TableAlignment;
use App\Models\LogEntry;
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
use Illuminate\Support\Carbon;
use UnitEnum;

/*
Logs (/logs): one list of every view, PDF download and edit of an officer or
civilian assessment, newest first. Views and downloads come from
assessment_access_log, edits (one row per save) from assessment_edits;
App\Models\LogEntry reads both together, each scoped before they are
combined. Resource request rows are recorded in assessment_access_log too, but
never listed here (Finishing Touches item 28): LogEntry leaves them out.

Who sees what (Filament_CMS_Design.md section 5, rule 5):
  - admins and super admins: every row - officer and civilian assessments,
    edits;
  - police admins with an agency: only rows about their own agency's
    officers' assessments, whoever made them - never a civilian assessment,
    never another agency's;
  - everyone else, and a police admin with no agency: no Logs item, and 403.

Above the table, the page's Show dropdown (ListLogs) and the search box, which
searches only the Who column (the person's name). Behind the Filters button,
ChangeLogFilters. All three only narrow the scoped rows.

A row names its form only by kind, submitted time and the start of its id -
no victim, offender or requester names, no phone numbers, no edit reason. The
forms are loaded with just those columns.

Read-only: no create, edit, delete or bulk action. Edit rows have a View
pop-up showing the edit's changes, which records a "Viewed Log" of the
assessment (AssessmentViews::logHistory, before the pop-up is sent, so a
failed write stops it opening). Access rows have no actions. Opening the page is not itself logged.
*/
class LogResource extends Resource
{
    use AllowsOnlyListedActions;

    protected static ?string $model = LogEntry::class;

    protected static ?string $slug = 'logs';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $modelLabel = 'log entry';

    protected static ?string $pluralModelLabel = 'Logs';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    // After Assessment review (1) and Civilian assessments (2). A police
    // admin finds it in their single "Police Admin" group instead.
    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    protected static ?int $navigationSort = 3;

    // Which pointer is set says what kind of assessment a row is about;
    // exactly one is (assessment_access_log's CHECK constraint, with resource
    // request rows left out by LogEntry; edits always point at an officer
    // assessment).
    private const KINDS = [
        'LawEnforcementDocumentID' => 'Officer Assessment',
        'PrivateDocumentID' => 'Civilian Assessment',
    ];

    // Grouping and order only - see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::LOGS : parent::getNavigationSort();
    }

    public static function canAccess(): bool
    {
        return self::canOpenLogs();
    }

    // Active admins and super admins, and active police admins with an
    // agency, by users.role.
    public static function canOpenLogs(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin)
            || $user?->managedAgencyId() !== null;
    }

    // The only thing scoping the rows; also scopes the record lookup behind
    // View. Never return an unscoped query here.
    public static function getEloquentQuery(): Builder
    {
        return LogEntry::visibleTo(Filament::auth()->user());
    }

    // The list, and View on edit rows. Both logs are append-only.
    protected static function allowedActions(): array
    {
        return ['view'];
    }

    // View only on an edit row the user may see.
    protected static function allowsRecord(string $action, Model $record): bool
    {
        return $record instanceof LogEntry
            && $record->isEdit()
            && static::getEloquentQuery()->whereKey($record->getKey())->exists();
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
                TextColumn::make('OccurredAt')
                    ->label('When')
                    ->dateTime('M j, Y g:i:s a')
                    ->timezone(Timezones::DISPLAY)
                    ->sortable(),

                // The only searchable column: the person's name, never
                // anything from the forms or their ids.
                TextColumn::make('user.name')
                    ->label('Who')
                    ->placeholder('Unknown')
                    ->searchable()
                    ->forceSearchCaseInsensitive(),

                TableAlignment::symbol(TextColumn::make('Action')
                    ->badge()
                    // A download with the follow-up log reads as a plain
                    // download to a police admin (LogAction::shownTo()).
                    ->formatStateUsing(fn (LogAction $state): string => $state->shownTo(Filament::auth()->user())->label())
                    ->color(fn (LogAction $state): string => $state->shownTo(Filament::auth()->user())->color())),

                TextColumn::make('record')
                    ->label('Record')
                    ->state(fn (LogEntry $record): string => self::KINDS[self::pointer($record)])
                    ->description(fn (LogEntry $record): string => self::recordLabel($record)),

                TableAlignment::symbol(TextColumn::make('changes_count')
                    ->label('Changes')
                    ->state(fn (LogEntry $record): ?int => $record->isEdit()
                        ? (int) $record->detail_changes_count + (int) $record->answer_changes_count
                        : null)
                    ->formatStateUsing(fn (int $state): string => $state.' '.str('field')->plural($state))
                    ->badge()
                    ->color('gray')),
            ])
            ->defaultSort('OccurredAt', 'desc')
            // Cards instead of a wide table on phones (team plan task 10).
            ->stackedOnMobile()
            // Only the two columns recordLabel() reads, so nothing personal is
            // even loaded.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'user',
                    'lawEnforcementAssessment:DocumentID,DateCreated',
                    'privateAssessment:DocumentID,DateCreated',
                ])
                ->withCount(['detailChanges', 'answerChanges']))
            ->recordActions([
                // Shows the edit's old and new values, so opening it is
                // recorded, as a look at the assessment's history ("Viewed
                // Log") - see App\Filament\Assessments\AssessmentViews.
                ViewAction::make()
                    ->modalHeading('View edit')
                    ->visible(fn (LogEntry $record): bool => $record->isEdit())
                    ->afterFormFilled(fn (LogEntry $record) => AssessmentViews::logHistory($record->edit->assessment)),
            ])
            ->emptyStateHeading('Nothing logged yet')
            ->emptyStateDescription('Views, PDF downloads and edits of assessments appear here.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogs::route('/'),
        ];
    }

    private static function pointer(LogEntry $record): string
    {
        foreach (array_keys(self::KINDS) as $column) {
            if ($record->{$column} !== null) {
                return $column;
            }
        }

        // Unreachable while the CHECK constraint holds.
        return 'LawEnforcementDocumentID';
    }

    // "Submitted Oct 20, 2026 3:15 pm · 1a2b3c4d", or "Removed · 1a2b3c4d"
    // if the assessment is somehow gone.
    private static function recordLabel(LogEntry $record): string
    {
        $column = self::pointer($record);

        $form = match ($column) {
            'LawEnforcementDocumentID' => $record->lawEnforcementAssessment,
            'PrivateDocumentID' => $record->privateAssessment,
        };

        $id = substr((string) $record->{$column}, 0, 8);

        if ($form === null) {
            return "Removed · {$id}";
        }

        $submitted = $form->getAttribute('DateCreated');

        if ($submitted === null) {
            return $id;
        }

        // Stored UTC, shown Eastern - see App\Support\Timezones.
        return 'Submitted '.Carbon::parse($submitted)->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a')." · {$id}";
    }
}
