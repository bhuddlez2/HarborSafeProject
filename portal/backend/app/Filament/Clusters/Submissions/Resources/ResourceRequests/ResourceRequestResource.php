<?php

namespace App\Filament\Clusters\Submissions\Resources\ResourceRequests;

use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\SubmissionsCluster;
use App\Models\County;
use App\Models\Resource as ResourceType;
use App\Models\ResourceRequestForm;
use App\Support\RecordPdf;
use App\Filament\Tables\EncryptedColumnSearch;
use App\Filament\Tables\RecordCards;
use App\Filament\Tables\TableAlignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
    use AllowsOnlyListedActions;

    protected static ?string $model = ResourceRequestForm::class;

    protected static ?string $cluster = SubmissionsCluster::class;

    protected static ?string $slug = 'resource-requests';

    protected static ?string $title = 'Resource Requests';

    protected static ?string $navigationLabel = 'Resource Requests';

    protected static ?string $modelLabel = 'resource request';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::SuperAdmin,
            UserRole::Secretary,
        );
    }

    // Never edited: a request is a statement from the public. Delete is for spam. Staff-started requests come from a follow-up page, never a Create button here.
    protected static function allowedActions(): array
    {
        // Requests are kept: only admins and super admins may delete one (spam), and its
        // follow-up and history go with it (Burchfield B56).
        return Filament::auth()->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin)
            ? ['view', 'delete', 'deleteAny']
            : ['view'];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    // The same record design as the assessment viewers, and the same partial
    // the PDF download renders (App\Support\RecordPdf).
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('record')
                    ->view(RecordPdf::RESOURCE_REQUEST)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Four columns, and a card on a phone (RecordCards): name and email
        // on top, county and resources, then the date. Where a form came from
        // is a small "Staff" tag under the date, shown only on forms staff
        // entered; website forms show nothing.
        return RecordCards::apply($table)
            ->searchPlaceholder('Search by Name or Email')
            ->columns([
                RecordCards::meta(RecordCards::dateAndTime(TextColumn::make('SubmissionDate')
                    ->label('Submitted')
                    ->sortable(), tag: fn (ResourceRequestForm $record): ?HtmlString => $record->Source === ResourceRequestForm::SOURCE_STAFF
                        ? RecordCards::staffTag()
                        : null)),

                // The email in grey under the name. The search box finds
                // either.
                RecordCards::title(TextColumn::make('FirstName')
                    ->label('Name')
                    ->searchable(query: EncryptedColumnSearch::query(ResourceRequestForm::class, ['FirstName', 'LastName', 'EmailAddress']))
                    ->formatStateUsing(fn (?string $state, ResourceRequestForm $record): string => trim(
                        ($state ?? '').' '.($record->LastName ?? ''),
                    ) ?: '-')
                    ->description(fn (ResourceRequestForm $record): ?HtmlString => RecordCards::email($record->EmailAddress))),

                TextColumn::make('county.Name')
                    ->label('County')
                    ->placeholder('-')
                    ->sortable(),

                // SafePhoneNumber is deliberately absent - see the class
                // comment. It lives in the detail view only.

                TableAlignment::symbol(TextColumn::make('resourceTypes.Name')
                    ->label('Interested in')
                    ->badge()
                    ->placeholder('-')
                    // Filament drops the items past the limit unless the list has line breaks.
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()),

                // Where the request's follow-up stands (Burchfield B58). It is
                // updated on its follow-up page, through the Follow-Up button.
                RecordCards::corner(TableAlignment::symbol(TextColumn::make('follow_up_status')
                    ->label('Follow-Up')
                    ->badge()
                    ->color('gray')
                    ->state(fn (ResourceRequestForm $record, ListResourceRequests $livewire): ?string => $livewire->followUpFor($record)?->Status->label())
                    ->placeholder('-'))),
            ])
            ->defaultSort('SubmissionDate', 'desc')
            ->filters([
                SelectFilter::make('Source')
                    ->label('Source')
                    ->options([
                        ResourceRequestForm::SOURCE_ONLINE => 'Online',
                        ResourceRequestForm::SOURCE_STAFF => 'Staff',
                    ]),

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
                // The request's follow-up page, where Update Follow-Up is
                // (Burchfield B58). Only while this user may work it:
                // followUpFor() is scoped by FollowUp::visibleTo().
                Action::make('follow_up')
                    ->label('Follow-Up')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('gray')
                    ->url(fn (ResourceRequestForm $record, ListResourceRequests $livewire): ?string => $livewire->followUpFor($record)?->url())
                    ->visible(fn (ResourceRequestForm $record, ListResourceRequests $livewire): bool => $livewire->followUpFor($record) !== null),

                // The plain form as a PDF, through its own logged route
                // (ResourceRequestPdfController), as civilian assessments do.
                ViewAction::make()
                    ->modalHeading('View Resource Request')
                    ->extraModalFooterActions(fn (ResourceRequestForm $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.resource-requests.pdf', $record->FormID))
                            ->openUrlInNewTab(),
                    ]),
                DeleteAction::make()
                    ->modalHeading('Delete this request?')
                    ->modalDescription('Someone asked for help with this form. Deleting it cannot be undone.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->modalHeading('Delete Selected Resource Requests'),
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
