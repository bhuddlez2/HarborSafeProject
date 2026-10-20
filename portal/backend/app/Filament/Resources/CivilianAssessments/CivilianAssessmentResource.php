<?php

namespace App\Filament\Resources\CivilianAssessments;

use App\Enums\OffenderRelationship;
use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\PrivateAssessment;
use App\Support\Timezones;
use App\Filament\Tables\TableAlignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    // Sorts after the law-enforcement review page.
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin);
    }

    // The same record design as the law-enforcement View pop-up and PDF. The
    // partial adds a "Submitted by" card when there is a named submitter.
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
                    ->timezone(Timezones::DISPLAY)
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

                // As on the law-enforcement tables (AssessmentColumns).
                TextColumn::make('OffenderVictimRelationship')
                    ->label('Relationship')
                    ->formatStateUsing(fn (?string $state, $record): ?string => OffenderRelationship::display($state, $record->OffenderVictimRelationshipOther))
                    ->placeholder('-'),

                // VictimSafePhoneNumber is deliberately absent - see the class
                // comment. It is in the detail view only.

                TableAlignment::symbol(TextColumn::make('risk_count')
                    ->label('Yes')
                    ->badge()
                    ->state(fn (PrivateAssessment $record): string => self::yesCount($record).' of 11')
                    ->color(fn (PrivateAssessment $record): string => match (true) {
                        self::yesCount($record) >= 4 => 'danger',
                        self::yesCount($record) >= 1 => 'warning',
                        default => 'gray',
                    })),
            ])
            ->defaultSort('DateCreated', 'desc')
            // Without this the Yes-answers column would issue a query per row.
            // submitterInfo is deliberately not eager-loaded: nothing in the
            // table reads it, and the detail view loads one record anyway.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('assessmentAnswers'))
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
                ViewAction::make()
                    ->extraModalFooterActions(fn (PrivateAssessment $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.civilian-assessments.pdf', $record->DocumentID))
                            ->openUrlInNewTab(),
                    ]),
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
