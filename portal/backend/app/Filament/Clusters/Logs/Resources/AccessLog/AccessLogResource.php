<?php

namespace App\Filament\Clusters\Logs\Resources\AccessLog;

use App\Enums\AssessmentAccess;
use App\Filament\Clusters\Logs\LogsCluster;
use App\Filament\Clusters\Logs\Resources\AccessLog\Pages\ListAccessLog;
use App\Filament\Tables\TableAlignment;
use App\Models\AssessmentAccessLog;
use App\Support\Timezones;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/*
The access log, the first tab of Logs (/logs/access, App\Filament\Clusters\Logs):
every view and PDF download recorded in assessment_access_log
(App\Models\AssessmentAccessLog) - who opened which form, how and when, so the
team can check that view logging works.

Two audiences, decided by AssessmentAccessLog::visibleTo() (getEloquentQuery
below), which is built on LawEnforcementAssessment::visibleTo():
  - admins: every row, of every kind;
  - police admins with an agency: only rows about their own agency's
    officers' assessments, whoever opened them - never a civilian assessment
    or resource request, never another agency's (section 5, rule 5). A police
    admin with no agency is refused, like Officers.

Read-only: the log is append-only, so there is no create, edit, delete, view
pop-up or bulk action. Opening this screen is not itself logged - it shows
nothing from the forms.

It names a record only by its submitted time and the start of its id, never
by anything in it: no victim, offender or requester names, no phone numbers.
The forms are loaded with just those two columns for that reason.

No table filters, so there is no Filters button or card. Above the table, the
page's one "Show" dropdown (All / Viewed / Downloaded PDF, ListAccessLog)
narrows the rows by action. The search box searches only the Who column (the
opener's name: partial, case-insensitive). Both only narrow the scoped rows
above.

Authorizes itself (canAccess below, by users.role and the police admin's
agency), so it is in resources.exclude in config/filament-shield.php.
*/
class AccessLogResource extends Resource
{
    protected static ?string $model = AssessmentAccessLog::class;

    // The first tab of Logs: /logs/access.
    protected static ?string $cluster = LogsCluster::class;

    protected static ?string $slug = 'access';

    protected static ?string $navigationLabel = 'Access log';

    protected static ?string $modelLabel = 'access';

    protected static ?string $pluralModelLabel = 'Access log';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedEye;

    // Tab order inside the cluster: Access log, then Change log.
    protected static ?int $navigationSort = 1;

    // Which pointer is set says what kind of form a row is about; exactly one
    // is (the table's CHECK constraint).
    private const KINDS = [
        'LawEnforcementDocumentID' => 'Officer assessment',
        'PrivateDocumentID' => 'Civilian assessment',
        'ResourceRequestFormID' => 'Resource request',
    ];

    // Active admins, and active police admins with an agency - the Logs
    // cluster's rule, shared by both tabs.
    public static function canAccess(): bool
    {
        return LogsCluster::canOpenLogs();
    }

    // The only thing scoping the rows. Never return an unscoped query here.
    // The page's Show dropdown (ListAccessLog) only narrows it further.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Filament::auth()->user());
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return false;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('AccessedAt')
                    ->label('When')
                    ->dateTime('M j, Y g:i:s a')
                    ->timezone(Timezones::DISPLAY)
                    ->sortable(),

                // The only searchable column: the opener's name, never
                // anything from the forms or their ids.
                TextColumn::make('user.name')
                    ->label('Who')
                    ->placeholder('Unknown')
                    ->searchable()
                    ->forceSearchCaseInsensitive(),

                TableAlignment::symbol(TextColumn::make('Action')
                    ->badge()
                    ->formatStateUsing(fn (AssessmentAccess $state): string => $state->label())
                    ->color(fn (AssessmentAccess $state): string => $state === AssessmentAccess::Download ? 'warning' : 'gray')),

                TextColumn::make('record_type')
                    ->label('Record type')
                    ->state(fn (AssessmentAccessLog $record): string => self::KINDS[self::pointer($record)]),

                TextColumn::make('record')
                    ->label('Record')
                    ->state(fn (AssessmentAccessLog $record): string => self::recordLabel($record)),
            ])
            ->defaultSort('AccessedAt', 'desc')
            // Only the two columns recordLabel() reads, so nothing personal is
            // even loaded. The resource request is on Feedback, which eager
            // loading reaches with its own query.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'user',
                'lawEnforcementAssessment:DocumentID,DateCreated',
                'privateAssessment:DocumentID,DateCreated',
                'resourceRequestForm:FormID,SubmissionDate',
            ]))
            // No table filters, so Filament draws no Filters button or card.
            // The page's one "Show" dropdown (ListAccessLog) replaces them.
            ->emptyStateHeading('Nothing opened yet')
            ->emptyStateDescription('Views and PDF downloads of assessments and resource requests appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessLog::route('/'),
        ];
    }

    private static function pointer(AssessmentAccessLog $record): string
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
    // when the form is gone (a resource request deleted as spam: that pointer
    // has no foreign key).
    private static function recordLabel(AssessmentAccessLog $record): string
    {
        $column = self::pointer($record);

        [$form, $submittedColumn] = match ($column) {
            'LawEnforcementDocumentID' => [$record->lawEnforcementAssessment, 'DateCreated'],
            'PrivateDocumentID' => [$record->privateAssessment, 'DateCreated'],
            'ResourceRequestFormID' => [$record->resourceRequestForm, 'SubmissionDate'],
        };

        $id = substr((string) $record->{$column}, 0, 8);

        if ($form === null) {
            return "Removed · {$id}";
        }

        $submitted = $form->getAttribute($submittedColumn);

        if ($submitted === null) {
            return $id;
        }

        // Stored UTC, shown Eastern - see App\Support\Timezones.
        return 'Submitted '.Carbon::parse($submitted)->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a')." · {$id}";
    }
}
