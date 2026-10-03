<?php

namespace App\Filament\Resources\LawEnforcementAssessments;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ViewLawEnforcementAssessment;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('DateCreated')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('VictimFirstName')
                    ->searchable(),
                TextColumn::make('VictimLastName')
                    ->searchable(),
                TextColumn::make('OffenderFirstName')
                    ->searchable(),
                TextColumn::make('OffenderLastName')
                    ->searchable(),
                TextColumn::make('OffenderVictimRelationship'),
                TextColumn::make('submitter.name')
                    ->visible(fn (): bool => (bool) Filament::auth()->user()?->hasActiveRole(
                        UserRole::PoliceAdmin,
                    )),
            ])
            ->defaultSort('DateCreated', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submitting officer')
                    ->schema([
                        TextEntry::make('submitter.name'),
                        TextEntry::make('submitter.lawEnforcementAgent.badge_number'),
                        TextEntry::make('submitter.lawEnforcementAgent.agency.name'),
                    ]),
                Section::make('Victim')
                    ->schema([
                        TextEntry::make('VictimFirstName'),
                        TextEntry::make('VictimLastName'),
                        TextEntry::make('VictimSex'),
                        TextEntry::make('VictimDOB'),
                        TextEntry::make('VictimSafePhoneNumber'),
                    ]),
                Section::make('Offender')
                    ->schema([
                        TextEntry::make('OffenderFirstName'),
                        TextEntry::make('OffenderLastName'),
                        TextEntry::make('OffenderSex'),
                        TextEntry::make('OffenderDOB'),
                        TextEntry::make('OffenderVictimRelationship'),
                    ]),
                Section::make('Risk indicators')
                    ->schema(
                        collect(NewAssessment::QUESTIONS)
                            ->map(fn (string $question, int $id): TextEntry => TextEntry::make("assessmentAnswers.RiskIndicator{$id}")
                                ->label($question)
                                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'))
                            ->values()
                            ->all(),
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLawEnforcementAssessments::route('/'),
            'view' => ViewLawEnforcementAssessment::route('/{record}'),
        ];
    }
}
