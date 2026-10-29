<?php

namespace App\Filament\Clusters\Content\Resources\Newsletters;

use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Enums\UserRole;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Forms\Components\DatabaseFileUpload;
use App\Filament\Forms\DateWindow;
use App\Support\Timezones;
use Closure;
use Illuminate\Support\Carbon;
use App\Models\Newsletter;
use App\Filament\Tables\TableAlignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
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
    use AllowsOnlyListedActions;

    /*
    The publish date's range (Finishing Touches item 26), in Eastern days:
    from the 1st of the month six months back up to today, or, with Schedule
    post ticked, up to the end of the month six months ahead. On 15 October
    2026 that is 1 April 2026 to today, or to 30 April 2027 when scheduled.
    The website hides an issue until its date (Newsletter::scopeOnWebsite()).
    */
    public const MONTHS_BACK = 6;

    public const MONTHS_AHEAD = 6;

    public static function earliestPublishDay(): string
    {
        return now(Timezones::DISPLAY)->subMonthsNoOverflow(self::MONTHS_BACK)->startOfMonth()->toDateString();
    }

    public static function latestPublishDay(bool $scheduled): string
    {
        $today = now(Timezones::DISPLAY);

        return $scheduled
            ? $today->addMonthsNoOverflow(self::MONTHS_AHEAD)->endOfMonth()->toDateString()
            : $today->toDateString();
    }

    private static function tooEarlyMessage(): string
    {
        return "Can't be before ".Carbon::parse(self::earliestPublishDay())->format('F j, Y').'.';
    }

    private static function tooLateMessage(bool $scheduled): string
    {
        return $scheduled
            ? "Can't be after ".Carbon::parse(self::latestPublishDay(true))->format('F j, Y').'.'
            : "Can't be in the future. Tick Schedule post to choose a later date.";
    }

    // An existing issue keeps its saved date unchecked, so an old one can
    // still be edited - unless that date is in the future and Schedule post
    // has been unticked.
    private static function keepsSavedDate(?Newsletter $record, Carbon $picked, bool $scheduled): bool
    {
        $day = $picked->toDateString();

        return $record?->issue_date !== null
            && $day === $record->issue_date->toDateString()
            && ($scheduled || $day <= self::latestPublishDay(false));
    }

    // A saved issue whose date hasn't arrived yet.
    private static function isScheduled(?Newsletter $record): bool
    {
        return $record?->issue_date !== null
            && $record->issue_date->toDateString() > self::latestPublishDay(false);
    }

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
            UserRole::SuperAdmin,
            UserRole::Secretary,
        );
    }

    // Add, edit and delete issues, one or several at a time. Nothing else.
    protected static function allowedActions(): array
    {
        return ['create', 'update', 'delete', 'deleteAny'];
    }

    // The Edit pop-up: the same steps, with Save always in the footer.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make(self::steps())->columnSpanFull(),
            ]);
    }

    /*
    The Create pop-up. Given as action steps rather than the resource form, so
    Filament puts Create inside the wizard on its last step (Preview) and drops
    the footer, "Create & create another" with it: a half-finished newsletter
    can't be saved. Used by the page header and the table's empty state.
    */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Add an Issue')
            ->steps(self::steps())
            ->createAnother(false)
            // Filament draws an action's wizard uncontained; keep the Edit
            // pop-up's look.
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard->contained());
    }

    /**
     * @return array<Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Issue')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('Autumn 2026')
                        ->columnSpanFull(),

                    DatePicker::make('issue_date')
                        ->label('Publish Date')
                        ->required()
                        // Six months back to today, or six months ahead
                        // with Schedule post. The calendar limits apply
                        // to a new newsletter; an existing one keeps its
                        // saved date and only a changed date is checked
                        // (DateWindow).
                        ->minDate(fn (?Newsletter $record): ?string => $record ? null : self::earliestPublishDay())
                        ->maxDate(fn (?Newsletter $record, Get $get): ?string => $record ? null : self::latestPublishDay((bool) $get('schedule_post')))
                        ->rule(fn (?Newsletter $record, Get $get): Closure => DateWindow::rule(
                            self::earliestPublishDay(...),
                            fn (): string => self::latestPublishDay((bool) $get('schedule_post')),
                            self::tooEarlyMessage(),
                            self::tooLateMessage((bool) $get('schedule_post')),
                            fn (Carbon $picked): bool => self::keepsSavedDate($record, $picked, (bool) $get('schedule_post')),
                        ))
                        ->validationMessages([
                            'after_or_equal' => fn (): string => self::tooEarlyMessage(),
                            'before_or_equal' => fn (Get $get): string => self::tooLateMessage((bool) $get('schedule_post')),
                        ])
                        ->helperText('Orders the list on the website, newest first.'),

                    // Not stored: a future publish date is what makes an
                    // issue scheduled, so Edit ticks it from the date.
                    Checkbox::make('schedule_post')
                        ->label('Schedule post')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (Checkbox $component, ?Newsletter $record) => $component->state(self::isScheduled($record)))
                        ->helperText('Allows a date up to 6 months ahead. The issue stays off the website until that date.')
                        ->columnSpanFull(),

                    Textarea::make('summary')
                        ->maxLength(500)
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('500 Character Limit'),
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
                                'isScheduled'   => $issueDate !== null
                                    && $issueDate->toDateString() > self::latestPublishDay(false),
                            ])->render());
                        })
                        ->columnSpanFull(),
                ]),
        ];
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
                    ->date('F Y')
                    ->sortable(),

                TextColumn::make('file.name')
                    ->label('File')
                    ->placeholder('None')
                    ->limit(30),

                TextColumn::make('file_size_bytes')
                    ->label('Size')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : ($state >= 1048576
                            ? round($state / 1048576, 1).' MB'
                            : round($state / 1024).' KB')),

                TableAlignment::symbol(IconColumn::make('is_published')
                    ->label('Published')
                    ->alignCenter()
                    ->boolean()
                    ->sortable()),
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
                self::createAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNewsletters::route('/'),
        ];
    }
}
