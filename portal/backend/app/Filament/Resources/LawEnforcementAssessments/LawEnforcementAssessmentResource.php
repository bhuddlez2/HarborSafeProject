<?php

namespace App\Filament\Resources\LawEnforcementAssessments;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
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
// submitting officer's only, and is NOT yet recorded in a change log (Phase 9).
// No create or delete: new assessments come only from the NewAssessment wizard.
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
                            ->options(['M' => 'Male', 'F' => 'Female', 'O' => 'Other'])
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
                            ->options(['M' => 'Male', 'F' => 'Female', 'O' => 'Other'])
                            ->required(),
                        DatePicker::make('OffenderDOB')->label('Date of birth'),
                        TextInput::make('OffenderVictimRelationship')->label('Relationship to victim')->maxLength(50),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('DateCreated')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i a')
                    ->sortable(),

                TextColumn::make('VictimLastName')
                    ->label('Victim')
                    ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                        ($record->VictimFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->searchable(['VictimFirstName', 'VictimLastName']),

                TextColumn::make('OffenderLastName')
                    ->label('Offender')
                    ->formatStateUsing(fn (?string $state, LawEnforcementAssessment $record): string => trim(
                        ($record->OffenderFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->description(fn (LawEnforcementAssessment $record): string => $record->OffenderVictimRelationship ?? '')
                    ->searchable(['OffenderFirstName', 'OffenderLastName']),

                TextColumn::make('risk_count')
                    ->label('Yes answers')
                    ->badge()
                    ->state(fn (LawEnforcementAssessment $record): string => self::yesCount($record).' of 11')
                    ->color(fn (LawEnforcementAssessment $record): string => match (true) {
                        self::yesCount($record) >= 4 => 'danger',
                        self::yesCount($record) >= 1 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('DateCreated', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter']))
            ->stackedOnMobile()
            ->emptyStateHeading('No assessments yet')
            ->emptyStateDescription('Completed LAP screenings will appear here. Use the home page to start one.')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->schema([
                        TextEntry::make('DateCreated')
                            ->label('Submitted')
                            ->dateTime('M j, Y g:i a'),
                        TextEntry::make('submitter.name')
                            ->label('Submitting officer')
                            ->placeholder('Unknown'),
                        TextEntry::make('DocumentID')
                            ->label('Record ID')
                            ->copyable(),
                    ])
                    ->columns(3),

                Section::make('Victim')
                    ->schema([
                        TextEntry::make('VictimFirstName')->label('First name'),
                        TextEntry::make('VictimLastName')->label('Last name'),
                        TextEntry::make('VictimSex')->label('Sex'),
                        TextEntry::make('VictimDOB')
                            ->label('Date of birth')
                            ->date('M j, Y')
                            ->placeholder('Not recorded'),
                        TextEntry::make('VictimSafePhoneNumber')
                            ->label('Safe phone number')
                            ->placeholder('Not recorded'),
                    ])
                    ->columns(3),

                Section::make('Offender')
                    ->schema([
                        TextEntry::make('OffenderFirstName')->label('First name'),
                        TextEntry::make('OffenderLastName')->label('Last name'),
                        TextEntry::make('OffenderSex')->label('Sex'),
                        TextEntry::make('OffenderDOB')
                            ->label('Date of birth')
                            ->date('M j, Y')
                            ->placeholder('Not recorded'),
                        TextEntry::make('OffenderVictimRelationship')
                            ->label('Relationship to victim')
                            ->placeholder('Not recorded'),
                    ])
                    ->columns(3),

                Section::make('Risk indicators')
                    ->description('The eleven Lethality Assessment questions, in the order they were asked.')
                    ->schema(self::riskIndicatorEntries())
                    ->columns(1),
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

    private static function riskIndicatorEntries(): array
    {
        return collect(NewAssessment::QUESTIONS)
            ->map(fn (string $question, int $id): IconEntry => IconEntry::make("assessmentAnswers.RiskIndicator{$id}")
                ->label($id.'. '.$question)
                ->boolean()
                ->trueColor('danger')
                ->falseColor('gray'))
            ->values()
            ->all();
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
