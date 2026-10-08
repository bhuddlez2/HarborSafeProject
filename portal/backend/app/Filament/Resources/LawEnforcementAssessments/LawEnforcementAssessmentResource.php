<?php

namespace App\Filament\Resources\LawEnforcementAssessments;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

// "My Assessments": read-only list and view of law-enforcement assessments.
// Officers get their own submissions, police admins get all of them, everyone
// else gets nothing. Access is canAccess() below plus the query scope, with
// LawEnforcementAssessmentPolicy authorizing each record. No create, edit or delete: new assessments come only from the
// NewAssessment wizard.
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

    // Officer Portal only (section 5): not admin, even though the policy lets
    // admin view these records - admin's all-submissions view is
    // AssessmentReviewResource, which shares that policy.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::LawEnforcement,
            UserRole::PoliceAdmin,
        );
    }

    // Scopes the list, and the record lookup behind the view page, so an
    // officer requesting someone else's record gets a 404. Unknown or
    // inactive roles match nothing.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user?->hasActiveRole(UserRole::PoliceAdmin)) {
            return $query;
        }

        if ($user?->hasActiveRole(UserRole::LawEnforcement)) {
            return $query->where('submitted_by', $user->getKey());
        }

        return $query->whereRaw('1 = 0');
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

                TextColumn::make('submitter.name')
                    ->label('Officer')
                    ->alignCenter()
                    ->placeholder('Unknown')
                    ->sortable()
                    ->visible(fn (): bool => (bool) Filament::auth()->user()?->hasActiveRole(
                        UserRole::PoliceAdmin,
                    )),

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
            ])
            ->defaultSort('DateCreated', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter']))
            ->stackedOnMobile()
            ->emptyStateHeading('No assessments yet')
            ->emptyStateDescription('Completed LAP screenings will appear here. Use the home page to start one.')
            ->recordActions([
                ViewAction::make()
                    ->extraModalFooterActions(fn (LawEnforcementAssessment $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.assessments.pdf', $record->DocumentID))
                            ->openUrlInNewTab(),
                    ]),
                EditAction::make(),
            ]);
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
