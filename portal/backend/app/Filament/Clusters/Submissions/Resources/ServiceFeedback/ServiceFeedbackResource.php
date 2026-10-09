<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback;

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Clusters\Submissions\SubmissionsCluster;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Filament\Forms\RequirementMarkers;
use App\Http\Requests\Public\ServiceFeedbackStoreRequest;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Support\Timezones;
use App\Filament\Tables\TableAlignment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
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

/*
Service feedback: submitted through the public website's form, or filled in by
an admin or secretary ("New feedback form" on the list page,
ListServiceFeedback) - typically from a form that arrived on paper. Source
tells them apart, shown as "Online" / "Staff".

NEVER EDITED. These are statements made by members of the public, so there is
no edit action anywhere - altering one would be falsifying a record. That
holds for staff entries too: a mistyped one is deleted and filled in again.
View and delete are the only things offered; delete exists for spam and for
those mistakes, and takes a staff entry's scan with it.

The form() below is used only by that create action. Its rules are
ServiceFeedbackStoreRequest::fieldRules(), the website's own, so a staff entry
passes exactly the checks an online one does.

SubmissionDate is stored in UTC for both kinds - online rows by the column's
useCurrent(), staff rows by the form - and shown in Eastern
(App\Support\Timezones).

The rating is 1-5 as submitted. The form's own validation is the only thing
that has ever constrained it, so a stored 0 or 9 displays as-is rather than
being quietly hidden.

Connection note: this table is on `Feedback`, the full-access connection, not
`FeedbackPublic`. The restricted user exists so the public POST endpoint cannot
read submissions back out; the panel is the opposite case and needs SELECT.
*/
class ServiceFeedbackResource extends Resource
{
    protected static ?string $model = ServiceFeedback::class;

    protected static ?string $cluster = SubmissionsCluster::class;

    protected static ?string $slug = 'service-feedback';

    protected static ?string $title = 'Service feedback';

    protected static ?string $navigationLabel = 'Service feedback';

    protected static ?string $modelLabel = 'service feedback';

    protected static ?string $pluralModelLabel = 'service feedback';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }

    // A count on the tab, so volume is visible without opening the page.
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    // The "New feedback form" pop-up. Only ever used to create a staff entry.
    public static function form(Schema $schema): Schema
    {
        $rules = ServiceFeedbackStoreRequest::fieldRules();

        return $schema
            ->components([
                Select::make('ServiceID')
                    ->label('Service')
                    // Not ->relationship(): the relation is on Feedback and
                    // Filament would resolve the option query elsewhere.
                    ->options(fn (): array => Service::query()
                        ->orderBy('Name')
                        ->pluck('Name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->rules($rules['ServiceID']),

                ToggleButtons::make('Rating')
                    ->label('Rating')
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                    ->inline()
                    ->helperText('1 = poor, 5 = excellent, as on the website.')
                    ->required()
                    ->rules($rules['Rating']),

                // Entered and shown in Eastern, saved as UTC - Filament
                // converts both ways because of ->timezone().
                DateTimePicker::make('SubmissionDate')
                    ->label('Date and time')
                    ->native()
                    ->seconds(false)
                    ->timezone(Timezones::DISPLAY)
                    ->default(fn () => now())
                    ->maxDate(fn () => now())
                    ->helperText('When the form was filled in. Defaults to now.')
                    ->required(),

                Textarea::make('Comment')
                    ->label('Comments')
                    ->rows(5)
                    ->maxLength(1000)
                    ->rules($rules['Comment'])
                    ->columnSpanFull(),

                DatabaseFileUpload::forScan('ScanFileID')
                    ->label(RequirementMarkers::optionalLabel('Scan of the form'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('service.Name')
                            ->label('Service')
                            ->placeholder('Not recorded'),

                        TextEntry::make('Rating')
                            ->label('Rating')
                            ->formatStateUsing(fn (?int $state): string => $state === null
                                ? 'Not recorded'
                                : $state.' out of 5'),

                        TextEntry::make('SubmissionDate')
                            ->label('Submitted')
                            ->dateTime('M j, Y g:i a')
                            ->timezone(Timezones::DISPLAY),

                        TextEntry::make('Source')
                            ->label('Source')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::sourceLabel($state))
                            ->color(fn (?string $state): string => $state === ServiceFeedback::SOURCE_STAFF ? 'info' : 'gray'),

                        TextEntry::make('EnteredBy')
                            ->label('Entered by')
                            ->state(fn (ServiceFeedback $record): ?string => $record->enteredByName())
                            ->placeholder('Unknown')
                            ->visible(fn (ServiceFeedback $record): bool => $record->isStaffEntry()),

                        TextEntry::make('ScanFileID')
                            ->label('Scan')
                            ->state('Open the scan')
                            ->url(fn (ServiceFeedback $record): string => route('staff.feedback-scans.show', ['file' => $record->ScanFileID]))
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->visible(fn (ServiceFeedback $record): bool => filled($record->ScanFileID)),

                        TextEntry::make('Comment')
                            ->label('Comment')
                            ->placeholder('No comment left')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    private static function sourceLabel(?string $source): string
    {
        return $source === ServiceFeedback::SOURCE_STAFF ? 'Staff' : 'Online';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('SubmissionDate')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Timezones::DISPLAY)
                    ->sortable(),

                TableAlignment::symbol(TextColumn::make('Source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::sourceLabel($state))
                    ->color(fn (?string $state): string => $state === ServiceFeedback::SOURCE_STAFF ? 'info' : 'gray')),

                TextColumn::make('service.Name')
                    ->label('Service')
                    ->placeholder('-')
                    ->sortable(),

                TableAlignment::symbol(TextColumn::make('Rating')
                    ->label('Rating')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state == 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : $state.'/5')
                    ->sortable()),

                TextColumn::make('Comment')
                    ->label('Comment')
                    ->placeholder('-')
                    ->limit(60)
                    // The cell truncates, so make the full text reachable
                    // without opening the record.
                    ->tooltip(fn (ServiceFeedback $record): ?string => $record->Comment)
                    ->searchable(),
            ])
            ->defaultSort('SubmissionDate', 'desc')
            ->filters([
                SelectFilter::make('Source')
                    ->label('Source')
                    ->options([
                        ServiceFeedback::SOURCE_ONLINE => 'Online',
                        ServiceFeedback::SOURCE_STAFF => 'Staff',
                    ]),

                SelectFilter::make('ServiceID')
                    ->label('Service')
                    ->options(fn (): array => Service::query()
                        ->orderBy('Name')
                        ->pluck('Name', 'id')
                        ->all()),

                Filter::make('low_ratings')
                    ->label('Low ratings only (1-2)')
                    ->query(fn (Builder $query): Builder => $query->where('Rating', '<=', 2)),

                Filter::make('with_comment')
                    ->label('Has a comment')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('Comment')
                        ->where('Comment', '!=', '')),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->modalHeading('Delete this feedback?')
                    ->modalDescription('This is a submission from a member of the public. Deleting it cannot be undone. Any attached scan is deleted with it.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Record by record, never one DELETE query, so each
                    // entry's deleted hook removes its scan.
                    DeleteBulkAction::make()->fetchSelectedRecords(),
                ]),
            ])
            ->emptyStateHeading('No feedback yet')
            ->emptyStateDescription('Feedback left through the website, and forms filled in here, appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceFeedback::route('/'),
        ];
    }
}
