<?php

namespace App\Filament\Clusters\Content\Resources\EventCategories;

use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Models\EventCategory;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/*
The lookup behind an event's category badge.

Deleting a category that events point at would violate the foreign key, so the
delete action is hidden while any event references it. Renaming is always safe
- events hold the id, and the public API serialises Name, so a rename
propagates to the website on the next request.

No bulk delete for the same reason: a bulk action cannot explain which row of
the selection was the problem.
*/
class EventCategoryResource extends Resource
{
    protected static ?string $model = EventCategory::class;

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $slug = 'categories';

    protected static ?string $title = 'Categories';

    protected static ?string $navigationLabel = 'Categories';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'Name';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('Name')
                    ->label('Category name')
                    ->required()
                    ->maxLength(250)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('Name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('Name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // The FK on events.category_id would reject this anyway;
                    // hiding it gives a clearer answer than a database error.
                    ->visible(fn (EventCategory $record): bool => ! $record->events()->exists()),
            ])
            ->emptyStateHeading('No categories yet')
            ->emptyStateDescription('Categories are optional. An event without one simply shows no badge.')
            ->emptyStateActions([
                CreateAction::make()->label('Add a category'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEventCategories::route('/'),
        ];
    }
}
