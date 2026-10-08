<?php

namespace App\Filament\Clusters\Content\Resources\Newsletters;

use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Models\Newsletter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/*
Newsletter issues listed on the website's Events & News page.

issue_date is a calendar month, not a moment, so it is a plain DatePicker with
no timezone handling - unlike Event's starts_at. The site labels issues by
month and sorts newest first.

file_size_bytes and file_pages are stored rather than derived so that listing
issues does not mean reading every PDF. The size is filled in automatically
from the upload; page count cannot be known without parsing the PDF, so it is
an optional field staff can type. Both are rendered conditionally on the site
("PDF - 2.3 MB - 4 pages", either half dropped when missing), so leaving them
empty is a supported state rather than a data problem.
*/
class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $slug = 'newsletters';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

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
                Wizard::make([
                    Step::make('Issue')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(200)
                                ->placeholder('Autumn 2026')
                                ->columnSpanFull(),

                            DatePicker::make('issue_date')
                                ->label('Issue date')
                                ->required()
                                ->helperText('Used to order the list, newest first.'),

                            Textarea::make('summary')
                                ->maxLength(500)
                                ->rows(3)
                                ->columnSpanFull()
                                ->helperText('Optional. A line about what is in this issue.'),
                        ])
                        ->columns(2),

                    Step::make('File & Visibility')
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            DatabaseFileUpload::forDocument('file_id')
                                ->label('PDF')
                                ->columnSpanFull(),

                            TextInput::make('file_pages')
                                ->label('Page count')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(9999)
                                ->helperText('Optional. Shown next to the file size.'),

                            // Shown for reference only. The value is derived from
                            // the uploaded file by a saving hook on the model, not
                            // from this field — dehydrated(false) keeps the form
                            // from writing it and the two from ever disagreeing.
                            TextInput::make('file_size_bytes')
                                ->label('File size (bytes)')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated(false)
                                ->helperText('Set automatically from the uploaded file.'),

                            Toggle::make('is_published')
                                ->label('Published')
                                ->helperText('Off keeps it off the website, and makes its PDF unreachable.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Step::make('Preview')
                        ->icon('heroicon-o-sparkles')
                        ->description('How your newsletter will appear on the website.')
                        ->schema([
                            /*
                            Same pattern as the event preview: Placeholder with a
                            ->content() closure that reads all earlier steps' values
                            via $get and renders a Blade template. HtmlString stops
                            Filament from escaping the rendered HTML as plain text.
                            */
                            Placeholder::make('newsletter_preview')
                                ->label('')
                                ->content(function (Get $get): HtmlString {
                                    // issue_date is stored as a plain date (no time),
                                    // so Carbon::parse() gives us a Carbon instance we
                                    // can format as "Month Year" in the template.
                                    $issueDate = filled($get('issue_date'))
                                        ? \Carbon\Carbon::parse($get('issue_date'))
                                        : null;

                                    // file_size_bytes is read-only and set by a model
                                    // hook on save, so it's only available here when
                                    // editing an existing newsletter — it will be null
                                    // when creating a new one.
                                    return new HtmlString(view('filament.forms.newsletter-preview', [
                                        'title'         => $get('title'),
                                        'summary'       => $get('summary'),
                                        'issueDate'     => $issueDate,
                                        'hasFile'       => filled($get('file_id')),
                                        'fileSizeBytes' => $get('file_size_bytes'),
                                        'filePages'     => $get('file_pages'),
                                        'isPublished'   => (bool) $get('is_published'),
                                    ])->render());
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('issue_date')
                    ->label('Issue')
                    ->alignCenter()
                    ->date('F Y')
                    ->sortable(),

                TextColumn::make('file.name')
                    ->label('File')
                    ->alignCenter()
                    ->placeholder('None')
                    ->limit(30),

                TextColumn::make('file_size_bytes')
                    ->label('Size')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : ($state >= 1048576
                            ? round($state / 1048576, 1).' MB'
                            : round($state / 1024).' KB')),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->alignCenter()
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                // Opens the stored PDF through the staff route, so a draft can
                // be checked before publishing.
                Action::make('download')
                    ->label('Open PDF')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Newsletter $record): ?string => $record->file_id
                        ? route('staff.content-files.show', ['file' => $record->file_id])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Newsletter $record): bool => filled($record->file_id)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No newsletters yet')
            ->emptyStateActions([
                CreateAction::make()->label('Add an issue'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNewsletters::route('/'),
        ];
    }
}
