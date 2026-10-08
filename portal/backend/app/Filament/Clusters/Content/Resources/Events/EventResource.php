<?php

namespace App\Filament\Clusters\Content\Resources\Events;

use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Content\Resources\Events\Pages\CreateEvent;
use App\Filament\Clusters\Content\Resources\Events\Pages\EditEvent;
use App\Filament\Clusters\Content\Resources\Events\Pages\ListEvents;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Models\Event;
use App\Models\EventCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/*
Events on the public website's Events & News page.

Three things here are not obvious and will cause visible bugs if changed
carelessly:

  1. TIMEZONE. starts_at/ends_at are stored UTC; staff think in Eastern. Both
     pickers declare ->timezone(Event::DISPLAY_TIMEZONE) so Filament converts
     on the way in and out. Drop that and every event is entered four or five
     hours off, differently either side of a DST boundary.

  2. DESCRIPTION IS AN ARRAY, NOT HTML. The site does description.map(...) over
     paragraph strings, so a rich-text editor here would render visible escaped
     markup on the live page. It is a plain textarea, split on blank lines
     going in and rejoined coming out.

  3. PUBLISHED AND CANCELLED ARE INDEPENDENT. A published event can also be
     cancelled - the site renders that as a badge with the registration link
     suppressed. They are not one status field, so they stay two toggles.
*/
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $slug = 'events';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

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
                Section::make('Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                        TextInput::make('summary')
                            ->maxLength(500)
                            ->helperText('One or two lines, shown in the event list.')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('Leave a blank line between paragraphs. Each paragraph is rendered separately on the website.')
                            // The column is a json array of paragraphs; the
                            // textarea is a single string. Convert both ways.
                            ->afterStateHydrated(fn (Textarea $component, mixed $state) => $component->state(
                                is_array($state) ? implode("\n\n", $state) : (string) ($state ?? ''),
                            ))
                            ->dehydrateStateUsing(fn (?string $state): ?array => self::toParagraphs($state)),

                        Select::make('category_id')
                            ->label('Category')
                            // Not ->relationship(): the relation crosses to the
                            // Content connection and Filament would resolve the
                            // option query on the default one.
                            ->options(fn (): array => EventCategory::query()
                                ->orderBy('Name')
                                ->pluck('Name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('No category'),

                        TextInput::make('recurrence')
                            ->maxLength(120)
                            ->placeholder('Annually in September')
                            ->helperText('Free text, shown as-is. Leave empty for a one-off event.'),
                    ])
                    ->columns(2),

                Section::make('When')
                    ->description('Times are Eastern. They are stored as UTC and rendered back in Eastern on the website.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Starts')
                            ->required()
                            ->seconds(false)
                            ->timezone(Event::DISPLAY_TIMEZONE),

                        DateTimePicker::make('ends_at')
                            ->label('Ends')
                            ->seconds(false)
                            ->timezone(Event::DISPLAY_TIMEZONE)
                            ->after('starts_at')
                            ->helperText('Optional.'),

                        Toggle::make('all_day')
                            ->label('All-day event')
                            ->helperText('The website hides the times and shows the date only.'),
                    ])
                    ->columns(2),

                Section::make('Location')
                    ->schema([
                        Toggle::make('location_is_virtual')
                            ->label('Virtual event')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('location_name')
                            ->label('Venue name')
                            ->maxLength(150)
                            ->visible(fn (callable $get): bool => ! $get('location_is_virtual')),

                        TextInput::make('location_address')
                            ->label('Address')
                            ->maxLength(255)
                            ->visible(fn (callable $get): bool => ! $get('location_is_virtual')),

                        TextInput::make('location_virtual_note')
                            ->label('Joining note')
                            ->maxLength(255)
                            ->placeholder('A link will be emailed to registrants')
                            ->visible(fn (callable $get): bool => (bool) $get('location_is_virtual'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Image')
                    ->schema([
                        DatabaseFileUpload::forImage('image_file_id')
                            ->label('Event image')
                            ->columnSpanFull(),

                        TextInput::make('image_alt')
                            ->label('Image description')
                            ->maxLength(255)
                            ->helperText('Describes the image for screen readers. Required whenever an image is set.')
                            ->required(fn (callable $get): bool => filled($get('image_file_id')))
                            ->columnSpanFull(),
                    ]),

                Section::make('Registration')
                    ->schema([
                        TextInput::make('registration_url')
                            ->label('Registration link')
                            ->url()
                            ->maxLength(255),

                        TextInput::make('registration_label')
                            ->label('Button text')
                            ->maxLength(100)
                            ->placeholder('Register now'),
                    ])
                    ->columns(2),

                Section::make('Visibility')
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText('Off keeps it off the website entirely. Its image is unreachable too.'),

                        Toggle::make('is_cancelled')
                            ->label('Cancelled')
                            ->helperText('Shown with a cancelled badge and no registration link. Independent of Published.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->weight('semibold'),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->alignCenter()
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Event::DISPLAY_TIMEZONE)
                    ->sortable(),

                TextColumn::make('category.Name')
                    ->label('Category')
                    ->alignCenter()
                    ->badge()
                    ->placeholder('None'),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->alignCenter()
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_cancelled')
                    ->label('Cancelled')
                    ->alignCenter()
                    ->boolean()
                    ->falseColor('gray')
                    ->trueColor('danger')
                    ->sortable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
                TernaryFilter::make('is_cancelled')->label('Cancelled'),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => EventCategory::query()
                        ->orderBy('Name')
                        ->pluck('Name', 'id')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('Events created here appear on the website once published.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    /*
    Splits the textarea into the paragraph array the column stores and the
    website maps over. Blank-line separated, inner single newlines preserved
    within a paragraph, and an empty box becomes null rather than [''] - the
    site guards on truthiness, and a one-empty-string array is truthy.
    */
    private static function toParagraphs(?string $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        $paragraphs = collect(preg_split('/\R{2,}/', trim($state)))
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->values()
            ->all();

        return $paragraphs === [] ? null : $paragraphs;
    }
}
