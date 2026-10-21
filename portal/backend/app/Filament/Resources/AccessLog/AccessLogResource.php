<?php

namespace App\Filament\Resources\AccessLog;

use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Resources\AccessLog\Pages\ListAccessLog;
use App\Filament\Tables\AssessmentFilters;
use App\Filament\Tables\TableAlignment;
use App\Models\AssessmentAccessLog;
use App\Support\Timezones;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/*
The access log: every view and PDF download recorded in assessment_access_log
(App\Models\AssessmentAccessLog) - who opened which form, how and when. Admins
only, so the team can check that view logging works.

Read-only: the log is append-only, so there is no create, edit, delete, view
pop-up or bulk action. Opening this screen is not itself logged - it shows
nothing from the forms.

It names a record only by its submitted time and the start of its id, never
by anything in it: no victim, offender or requester names, no phone numbers.
The forms are loaded with just those two columns for that reason.

Authorizes itself (canAccess below, by users.role), so it is in
resources.exclude in config/filament-shield.php.
*/
class AccessLogResource extends Resource
{
    protected static ?string $model = AssessmentAccessLog::class;

    protected static ?string $slug = 'access-log';

    protected static ?string $navigationLabel = 'Access log';

    protected static ?string $modelLabel = 'access';

    protected static ?string $pluralModelLabel = 'Access log';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedEye;

    // After Change log (3). Admin only, so it never appears in the police
    // admin's group (App\Filament\PoliceAdminNavigation).
    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    protected static ?int $navigationSort = 4;

    // Which pointer is set says what kind of form a row is about; exactly one
    // is (the table's CHECK constraint).
    private const KINDS = [
        'LawEnforcementDocumentID' => 'Officer assessment',
        'PrivateDocumentID' => 'Civilian assessment',
        'ResourceRequestFormID' => 'Resource request',
    ];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin);
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

                TextColumn::make('user.name')
                    ->label('Who')
                    ->placeholder('Unknown'),

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
            ->filters([
                // Eastern days, turned into a UTC range.
                AssessmentFilters::dateRange('accessed_at', 'AccessedAt', 'Opened'),

                SelectFilter::make('AccessedBy')
                    ->label('Who')
                    // Only people who appear in the log.
                    ->relationship('user', 'name', fn (Builder $query): Builder => $query
                        ->whereIn('id', AssessmentAccessLog::query()->select('AccessedBy')))
                    ->searchable(),

                SelectFilter::make('Action')
                    ->options(collect(AssessmentAccess::cases())
                        ->mapWithKeys(fn (AssessmentAccess $action): array => [$action->value => $action->label()])
                        ->all()),

                SelectFilter::make('record_type')
                    ->label('Record type')
                    ->options(self::KINDS)
                    ->query(fn (Builder $query, array $data): Builder => array_key_exists($data['value'] ?? '', self::KINDS)
                        ? $query->whereNotNull($data['value'])
                        : $query),
            ])
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
