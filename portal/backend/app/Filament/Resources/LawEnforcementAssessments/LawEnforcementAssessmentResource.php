<?php

namespace App\Filament\Resources\LawEnforcementAssessments;

use App\Enums\UserRole;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Tables\AssessmentFilters;
use App\Models\LawEnforcementAssessment;
use App\Services\AssessmentEditor;
use App\Support\AssessmentFields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

// "My Assessments": list, view and edit of law-enforcement assessments, for
// officers only, each seeing their own submissions. Police admins and admins
// review submissions in AssessmentReviewResource instead (admin all, police
// admin own agency). Access is canAccess() below plus the
// LawEnforcementAssessment::visibleTo() query scope, with
// LawEnforcementAssessmentPolicy authorizing each record. Edit is the
// submitting officer's only, needs a reason, and goes through
// App\Services\AssessmentEditor, which writes the change log - the models
// refuse any other update. No create or delete: new assessments come only from
// the NewAssessment wizard.
class LawEnforcementAssessmentResource extends Resource
{
    protected static ?string $model = LawEnforcementAssessment::class;

    // Same URL the former placeholder page used.
    protected static ?string $slug = 'police/assessments';

    protected static ?string $navigationLabel = 'My Assessments';

    protected static ?string $modelLabel = 'assessment';

    protected static ?string $pluralModelLabel = 'My Assessments';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 4;

    // Officers only (section 5). Police admins and admins are kept out even
    // though the policy lets them view some of these records: their view is
    // AssessmentReviewResource, which shares that policy.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::LawEnforcement,
        );
    }

    // Scopes the list, and the record lookup behind the view page, so an
    // officer requesting someone else's record gets a 404.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Filament::auth()->user());
    }

    // The edit pop-up. Details and answers are saved by AssessmentEditor, not
    // by Filament directly - see the EditAction in table().
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Victim')
                    ->schema([
                        TextInput::make('VictimFirstName')->label('First name')->required()->maxLength(50),
                        TextInput::make('VictimLastName')->label('Last name')->required()->maxLength(50),
                        Select::make('VictimSex')
                            ->label('Sex')
                            ->native()
                            ->options(AssessmentFields::SEX_OPTIONS)
                            ->required(),
                        DatePicker::make('VictimDOB')->label('Date of birth'),
                        TextInput::make('VictimSafePhoneNumber')->label('Safe contact number')->maxLength(20),
                    ]),
                Section::make('Offender')
                    ->schema([
                        TextInput::make('OffenderFirstName')->label('First name')->required()->maxLength(50),
                        TextInput::make('OffenderLastName')->label('Last name')->required()->maxLength(50),
                        Select::make('OffenderSex')
                            ->label('Sex')
                            ->native()
                            ->options(AssessmentFields::SEX_OPTIONS)
                            ->required(),
                        DatePicker::make('OffenderDOB')->label('Date of birth'),
                        TextInput::make('OffenderVictimRelationship')->label('Relationship to victim')->maxLength(50),
                    ]),
                Section::make('Risk indicators')
                    ->schema(
                        collect(NewAssessment::QUESTIONS)
                            ->map(fn (string $question, int $id): Toggle => Toggle::make("RiskIndicator{$id}")
                                ->label($id.'. '.$question))
                            ->values()
                            ->all(),
                    ),
                Section::make('Reason for change')
                    ->schema([
                        Textarea::make('reason')
                            ->hiddenLabel()
                            ->placeholder("e.g. Corrected the spelling of the victim's last name")
                            ->helperText('Recorded with this edit in the change history, which cannot be altered.')
                            ->required()
                            ->maxLength(255)
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AssessmentFilters::apply($table
            ->columns([
                TextColumn::make('DateCreated')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i a')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('VictimLastName')
                    ->label('Victim')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                        ($record->VictimFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->searchable(['VictimFirstName', 'VictimLastName']),

                TextColumn::make('OffenderLastName')
                    ->label('Offender')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                        ($record->OffenderFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->description(fn (LawEnforcementAssessment $record): string => $record->OffenderVictimRelationship ?? '')
                    ->searchable(['OffenderFirstName', 'OffenderLastName']),

                TextColumn::make('risk_count')
                    ->label('Yes answers')
                    ->badge()
                    ->alignCenter()
                    ->state(fn (LawEnforcementAssessment $record): string => self::yesCount($record).' of 11')
                    ->color(fn (LawEnforcementAssessment $record): string => match (true) {
                        self::yesCount($record) >= 4 => 'danger',
                        self::yesCount($record) >= 1 => 'warning',
                        default => 'gray',
                    }),

                ChangeHistory::amendedColumn(),
            ])
            ->defaultSort('DateCreated', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter'])->withCount('edits'))
            ->stackedOnMobile()
            ->emptyStateHeading('No assessments yet')
            ->emptyStateDescription('Completed LAP screenings will appear here. Use the home page to start one.')
            ->recordActions([
                ChangeHistory::action(),
                ViewAction::make()
                    ->extraModalFooterActions(fn (LawEnforcementAssessment $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.assessments.pdf', $record->DocumentID))
                            ->openUrlInNewTab(),
                    ]),
                EditAction::make()
                    ->modalHeading('Edit assessment')
                    ->modalSubmitActionLabel('Save edit')
                    // The answers live on _assessment_answers, not this record,
                    // and the reason always starts empty.
                    ->mutateRecordDataUsing(function (array $data, LawEnforcementAssessment $record): array {
                        foreach (AssessmentEditor::answerFields() as $field) {
                            $data[$field] = (bool) $record->assessmentAnswers?->{$field};
                        }

                        return [...$data, 'reason' => null];
                    })
                    ->using(function (LawEnforcementAssessment $record, array $data, EditAction $action): LawEnforcementAssessment {
                        $edit = AssessmentEditor::apply($record, $data, $data, $data['reason'], Filament::auth()->user());

                        if ($edit === null) {
                            Notification::make()
                                ->title('No changes to save')
                                ->body('Nothing in the assessment was different, so no edit was recorded.')
                                ->info()
                                ->send();

                            $action->halt();
                        }

                        return $record;
                    })
                    ->successNotificationTitle('Edit saved and recorded in the change history'),
            ]), reviewScreen: false);
    }

    // Returns the infolist schema for displaying assessment details.
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                // points to the custom assessment record view component design.
                ViewEntry::make('record')
                    ->view('filament.infolists.components.assessment-record')
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        // No 'view' or 'edit' pages: both actions open inline modals.
        // Don't remove getPages; it's required by Filament to know which pages exist for this resource.
        return [
            'index' => ListLawEnforcementAssessments::route('/'),
        ];
    }

    private static function yesCount(LawEnforcementAssessment $record): int
    {
        $answers = $record->assessmentAnswers;

        if (! $answers) {
            return 0;
        }

        return collect(array_keys(NewAssessment::QUESTIONS))
            ->filter(fn (int $id): bool => (bool) $answers->{"RiskIndicator{$id}"})
            ->count();
    }
}
