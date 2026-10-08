<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback;

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Clusters\Submissions\SubmissionsCluster;
use App\Models\Service;
use App\Models\ServiceFeedback;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
Service feedback submitted through the public website's form.

READ-ONLY ON PURPOSE. These are statements made by members of the public, so
there is no edit action anywhere - altering one would be falsifying a record.
View and delete are the only things offered, and delete exists for spam.

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
                            ->dateTime('M j, Y g:i a'),

                        TextEntry::make('Comment')
                            ->label('Comment')
                            ->placeholder('No comment left')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('SubmissionDate')
                    ->label('Submitted')
                    ->alignCenter()
                    ->dateTime('M j, Y g:i a')
                    ->sortable(),

                TextColumn::make('service.Name')
                    ->label('Service')
                    ->alignCenter()
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('Rating')
                    ->label('Rating')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state == 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : $state.'/5')
                    ->sortable(),

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
                    ->modalDescription('This is a submission from a member of the public. Deleting it cannot be undone.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No feedback yet')
            ->emptyStateDescription('Feedback left through the website appears here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceFeedback::route('/'),
        ];
    }
}
