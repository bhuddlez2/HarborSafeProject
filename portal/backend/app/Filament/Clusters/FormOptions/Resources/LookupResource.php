<?php

namespace App\Filament\Clusters\FormOptions\Resources;

use App\Enums\UserRole;
use App\Filament\Clusters\FormOptions\FormOptionsCluster;
use App\Support\Timezones;
use App\Filament\Tables\TableAlignment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/*
Shared behaviour for the three dropdown lookups behind the public forms:
services, resource types and counties.

All three are the same shape - an auto-increment id, a Name, and a ChangeDate -
and all three have the same two rules, which is why this base exists rather
than three near-identical files:

  1. RENAMING IS SAFE, DELETING IS NOT. Submissions store the id, so renaming
     a row propagates to the website and to every existing submission's
     display without breaking anything. Deleting a row that submissions point
     at violates a foreign key. Each subclass says how to detect that, and the
     delete action hides itself rather than letting the database refuse.

  2. ChangeDate IS MAINTAINED HERE. The column exists on all three tables and
     nothing was writing it. It is set on create and on edit, so there is a
     record of when an option last changed.

Edits here are live immediately: /api/public/services, /resources and
/counties read these same tables, so the website's dropdowns change on the
next request with no deploy. That is the point of the page.
*/
abstract class LookupResource extends Resource
{
    protected static ?string $cluster = FormOptionsCluster::class;

    protected static ?string $recordTitleAttribute = 'Name';

    // How the public form labels this list, used in the empty state.
    abstract protected static function formDescription(): string;

    // True when at least one submission references this row, which makes it
    // undeletable. Each lookup is referenced from a different place.
    abstract protected static function isInUse(Model $record): bool;

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
                    ->label('Option name')
                    ->required()
                    ->maxLength(250)
                    ->helperText('This is the wording the public sees in the dropdown.')
                    ->columnSpanFull(),

                // Stamped on save rather than shown. Hidden keeps it in the
                // form state so it is actually written.
                TextInput::make('ChangeDate')
                    ->hidden()
                    ->dehydrateStateUsing(fn (): string => now()->toDateTimeString()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('Name')
                    ->label('Option')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ChangeDate')
                    ->label('Last changed')
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Timezones::DISPLAY)
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->defaultSort('Name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // A foreign key would reject this anyway; hiding the
                    // action explains why far better than a database error.
                    ->visible(fn (Model $record): bool => ! static::isInUse($record)),
            ])
            // No bulk delete: a bulk action cannot say which row of the
            // selection was still in use.
            ->emptyStateHeading('No options yet')
            ->emptyStateDescription(static::formDescription())
            ->emptyStateActions([
                CreateAction::make()->label('Add an option'),
            ]);
    }
}
