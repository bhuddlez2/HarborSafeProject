<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests;

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\SubmissionsCluster;
use App\Models\County;
use App\Models\Resource as ResourceType;
use App\Models\ResourceRequestForm;
use App\Support\Timezones;
use App\Filament\Tables\TableAlignment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
Resource requests submitted through the public website's form.

THIS IS THE MOST SENSITIVE TABLE IN THE PANEL OUTSIDE THE ASSESSMENTS. A row
is a named person, their email, and a phone number they have told us is safe to
call, in the context of a domestic-violence service. Treat it accordingly:

  - Read-only. No edit action, ever. Correcting someone's own words is not
    this page's job.
  - SafePhoneNumber is NOT shown in the table. It is in the detail view only,
    so a phone number is never left sitting on a screen someone walked away
    from, and never caught in a screen-share of the list.
  - "Safe" is the operative word. The person chose that number because other
    numbers are not safe. Anything built on top of this data - an export, a
    notification, a mail merge - has to carry that assumption forward.

Deleting is offered because requests get actioned and because spam arrives,
but the modal says plainly that it cannot be undone.

ResourceTypeID was dropped in migration 2026_09_15_090000 and replaced by the
resource_request_resource_types pivot, so "resources of interest" is a
many-to-many and is rendered as a list rather than one value.
*/
class ResourceRequestResource extends Resource
{
    protected static ?string $model = ResourceRequestForm::class;

    protected static ?string $cluster = SubmissionsCluster::class;

    protected static ?string $slug = 'resource-requests';

    protected static ?string $title = 'Resource requests';

    protected static ?string $navigationLabel = 'Resource requests';

    protected static ?string $modelLabel = 'resource request';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who asked')
                    ->schema([
                        TextEntry::make('FirstName')->label('First name'),

                        TextEntry::make('LastName')
                            ->label('Last name')
                            ->placeholder('Not given'),

                        TextEntry::make('SubmissionDate')
                            ->label('Submitted')
                            ->dateTime('M j, Y g:i a')
                            ->timezone(Timezones::DISPLAY),
                    ])
                    ->columns(3),

                Section::make('How to reach them')
                    ->description('This number is the one they told us is safe to call. Other numbers may not be.')
                    ->schema([
                        TextEntry::make('EmailAddress')
                            ->label('Email')
                            ->placeholder('Not given')
                            ->copyable(),

                        TextEntry::make('SafePhoneNumber')
                            ->label('Safe phone number')
                            ->placeholder('Not given')
                            ->copyable(),

                        TextEntry::make('county.Name')
                            ->label('County')
                            ->placeholder('Not given'),
                    ])
                    ->columns(3),

                Section::make('What they asked for')
                    ->schema([
                        TextEntry::make('resourceTypes.Name')
                            ->label('Resources of interest')
                            ->badge()
                            ->placeholder('None selected'),

                        TextEntry::make('Message')
                            ->label('Message')
                            ->placeholder('No message left')
                            ->columnSpanFull(),
                    ]),
            ]);
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

                TextColumn::make('FirstName')
                    ->label('Name')
                    ->searchable(['FirstName', 'LastName'])
                    ->formatStateUsing(fn (?string $state, ResourceRequestForm $record): string => trim(
                        ($state ?? '').' '.($record->LastName ?? ''),
                    ) ?: '-'),

                TextColumn::make('county.Name')
                    ->label('County')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('EmailAddress')
                    ->label('Email')
                    ->placeholder('-')
                    ->searchable()
                    ->copyable(),

                // SafePhoneNumber is deliberately absent - see the class
                // comment. It lives in the detail view only.

                TableAlignment::symbol(TextColumn::make('resourceTypes.Name')
                    ->label('Interested in')
                    ->badge()
                    ->placeholder('-')
                    ->limitList(2)
                    ->expandableLimitedList()),
            ])
            ->defaultSort('SubmissionDate', 'desc')
            ->filters([
                SelectFilter::make('CountyID')
                    ->label('County')
                    ->options(fn (): array => County::query()
                        ->orderBy('Name')
                        ->pluck('Name', 'id')
                        ->all()),

                SelectFilter::make('resource_type')
                    ->label('Resource of interest')
                    ->options(fn (): array => ResourceType::query()
                        ->orderBy('Name')
                        ->pluck('Name', 'id')
                        ->all())
                    // Hand-rolled rather than ->relationship(): the pivot
                    // lives on the Feedback connection and Filament's
                    // relationship filter would resolve it on the default one.
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas(
                            'resourceTypes',
                            fn (Builder $inner): Builder => $inner->whereKey($data['value']),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->modalHeading('Delete this request?')
                    ->modalDescription('Someone asked for help with this form. Deleting it cannot be undone.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No requests yet')
            ->emptyStateDescription('Requests sent through the website appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceRequests::route('/'),
        ];
    }
}
