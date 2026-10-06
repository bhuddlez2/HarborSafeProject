<?php

namespace App\Filament\Resources\AssessmentReview;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/*
All law-enforcement assessment submissions, for police admins and admins.

This page exists because section 5 of Filament_CMS_Design.md grants both roles
"LE assessments: view all", and until now the only way an admin could see any
assessment was through the Officer Portal's own screens - which are officer
tools and no longer shown to admins.

VIEW-ONLY, WITHOUT EXCEPTION. Section 5 rule 1: only the submitting officer may
ever edit a submitted assessment, and police_admin and admin are view-only. So
there is no edit action, no delete action, no bulk action, and no create - the
wizard at App\Filament\Pages\NewAssessment is the only way a row is ever
written, and it is officers-only.

Deliberately NOT a replacement for the officer-facing My Assessments page
(App\Filament\Resources\LawEnforcementAssessments), which is scoped to the
signed-in officer. This one is the all-submissions view.

Secretaries must never reach this: rule 3, secretary never sees assessment PII,
neither civilian nor law-enforcement.
*/
class AssessmentReviewResource extends Resource
{
    protected static ?string $model = LawEnforcementAssessment::class;

    protected static ?string $slug = 'assessment-review';

    protected static ?string $title = 'Assessment review';

    protected static ?string $navigationLabel = 'Assessment review';

    protected static ?string $modelLabel = 'assessment';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    // Its own group, not "Content management": police_admin reaches this page
    // and nothing else in the panel's content side, so filing it under content
    // would be misleading for half its audience.
    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::PoliceAdmin,
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('record')
                    ->view('filament.infolists.components.assessment-record')
                    ->columnSpanFull(),
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

                TextColumn::make('submitter.name')
                    ->label('Officer')
                    ->placeholder('Unknown')
                    ->sortable(),

                // The screening result the eleven answers add up to. Counted
                // in PHP rather than SQL so the column list stays readable;
                // the relation is eager-loaded below.
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
            // Without this the Yes-answers column would issue a query per row.
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter']))
            ->stackedOnMobile()
            ->filters([
                SelectFilter::make('submitted_by')
                    ->label('Officer')
                    ->relationship('submitter', 'name')
                    ->searchable(),
            ])
            // View only - see the class comment. No edit, delete or bulk
            // actions anywhere on this resource.
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No assessments submitted yet')
            ->emptyStateDescription('Assessments submitted by officers through the portal appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessmentReview::route('/'),
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
