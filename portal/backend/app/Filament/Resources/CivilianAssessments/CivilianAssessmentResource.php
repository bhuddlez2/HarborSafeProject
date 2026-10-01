<?php

namespace App\Filament\Resources\CivilianAssessments;

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\PrivateAssessment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/*
Assessments submitted by members of the public through the anonymous civilian
flow (portal/frontend, at its root).

WHY THIS EXISTS: section 5 of Filament_CMS_Design.md grants admin
"Civilian assessments: view", and nothing in the panel provided it. Phase 8
built the law-enforcement review page and missed this one, so a civilian
submission landed in the database and was invisible to everyone - which is how
it was reported.

ADMIN ONLY. Not secretary (rule 3: secretary never sees assessment PII, civilian
or law-enforcement). Not law_enforcement or police_admin either - the matrix
gives them law-enforcement submissions only, and these are a different
population who submitted under a promise of confidentiality, not case records.

VIEW ONLY, WITH NO DELETE. Rule 1 says nobody may alter a submitted assessment.
Unlike the public forms under Submissions, there is deliberately no delete
action here: a feedback form gets deleted for spam, but a danger assessment is
a safety record and removing it is not a panel-shaped decision.

TWO THINGS ABOUT THIS DATA THAT ARE NOT OBVIOUS:

  1. MOST OF THESE ARE ANONYMOUS. SubmissionID is nullable, and the flow only
     collects submitter contact details when someone is filling it in for
     another person AND explicitly declines anonymity. A null submitter is the
     normal case, not missing data.

  2. VictimSafePhoneNumber IS THE WHOLE POINT AND THE WHOLE RISK. It is kept
     out of the table and shown only in the detail view, for the same reason as
     the resource requests: "safe" means the person's other numbers are not
     safe, and that number should not sit on an unattended screen or appear in
     a screen-share of a list.
*/
class CivilianAssessmentResource extends Resource
{
    protected static ?string $model = PrivateAssessment::class;

    protected static ?string $slug = 'civilian-assessments';

    protected static ?string $title = 'Civilian assessments';

    protected static ?string $navigationLabel = 'Civilian assessments';

    protected static ?string $modelLabel = 'civilian assessment';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    // Sorts after the law-enforcement review page.
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin);
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

                        TextEntry::make('DocumentID')
                            ->label('Record ID')
                            ->copyable(),
                    ])
                    ->columns(2),

                Section::make('Who submitted it')
                    ->description('Contact details are only collected when someone submits on behalf of another person and chooses not to be anonymous. An anonymous submission is the normal case.')
                    ->schema([
                        TextEntry::make('submitterInfo.SubmitterFirstName')
                            ->label('First name')
                            ->placeholder('Anonymous'),

                        TextEntry::make('submitterInfo.SubmitterLastName')
                            ->label('Last name')
                            ->placeholder('Anonymous'),

                        TextEntry::make('submitterInfo.SubmitterEmail')
                            ->label('Email')
                            ->placeholder('Not given')
                            ->copyable(),

                        TextEntry::make('submitterInfo.SubmitterPhoneNumber')
                            ->label('Phone')
                            ->placeholder('Not given')
                            ->copyable(),
                    ])
                    ->columns(2),

                Section::make('Victim')
                    ->schema([
                        TextEntry::make('VictimFirstName')->label('First name'),
                        TextEntry::make('VictimLastName')->label('Last name'),
                        TextEntry::make('VictimSex')->label('Sex')->placeholder('Not recorded'),
                        TextEntry::make('VictimDOB')
                            ->label('Date of birth')
                            ->date('M j, Y')
                            ->placeholder('Not recorded'),
                        TextEntry::make('VictimSafePhoneNumber')
                            ->label('Safe phone number')
                            ->placeholder('Not given')
                            ->copyable()
                            ->helperText('The number they told us is safe to call. Others may not be.'),
                    ])
                    ->columns(3),

                Section::make('Offender')
                    ->schema([
                        TextEntry::make('OffenderFirstName')->label('First name'),
                        TextEntry::make('OffenderLastName')->label('Last name'),
                        TextEntry::make('OffenderSex')->label('Sex')->placeholder('Not recorded'),
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
                    ->formatStateUsing(fn (?string $state, PrivateAssessment $record): string => trim(
                        ($record->VictimFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->searchable(['VictimFirstName', 'VictimLastName']),

                TextColumn::make('OffenderLastName')
                    ->label('Offender')
                    ->formatStateUsing(fn (?string $state, PrivateAssessment $record): string => trim(
                        ($record->OffenderFirstName ?? '').' '.($state ?? ''),
                    ) ?: '-')
                    ->searchable(['OffenderFirstName', 'OffenderLastName']),

                // VictimSafePhoneNumber is deliberately absent - see the class
                // comment. It is in the detail view only.

                IconColumn::make('SubmissionID')
                    ->label('Named')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedUser)
                    ->falseIcon(Heroicon::OutlinedUserMinus)
                    ->falseColor('gray')
                    ->tooltip(fn (PrivateAssessment $record): string => $record->SubmissionID
                        ? 'Submitted with contact details'
                        : 'Anonymous submission'),

                TextColumn::make('risk_count')
                    ->label('Yes answers')
                    ->badge()
                    ->state(fn (PrivateAssessment $record): string => self::yesCount($record).' of 11')
                    ->color(fn (PrivateAssessment $record): string => match (true) {
                        self::yesCount($record) >= 4 => 'danger',
                        self::yesCount($record) >= 1 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('DateCreated', 'desc')
            // Without this the Yes-answers column and the Named icon would
            // each issue a query per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['assessmentAnswers', 'submitterInfo']))
            ->filters([
                Filter::make('high_risk')
                    ->label('Four or more yes answers')
                    // Counted in SQL here rather than in PHP, because a filter
                    // has to narrow the query itself, not the loaded page.
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'assessmentAnswers',
                        fn (Builder $inner): Builder => $inner->whereRaw(
                            '('.implode(' + ', array_map(
                                fn (int $id): string => "COALESCE(RiskIndicator{$id}, 0)",
                                array_keys(NewAssessment::QUESTIONS),
                            )).') >= 4',
                        ),
                    )),

                Filter::make('named')
                    ->label('Submitted with contact details')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('SubmissionID')),
            ])
            // View only - no edit, no delete, no bulk actions. See the class
            // comment.
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No civilian assessments yet')
            ->emptyStateDescription('Assessments submitted through the public civilian flow appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCivilianAssessments::route('/'),
        ];
    }

    /*
    One entry per risk indicator, labelled with the question text rather than
    "RiskIndicator7". The wording comes from NewAssessment::QUESTIONS, which is
    also the officer wizard's source and matches
    portal/frontend/app/lib/lethality-questions.js - so this screen cannot
    drift from the question that was actually asked.
    */
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

    private static function yesCount(PrivateAssessment $record): int
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
