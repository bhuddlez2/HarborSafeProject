<?php

namespace App\Filament\Resources\CivilianAssessments;

use App\Filament\Assessments\ChangeHistory;
use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Enums\AssessmentAccess;
use App\Enums\UserRole;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Models\AssessmentAccessLog;
use App\Models\PrivateAssessment;
use App\Support\HighRisk;
use App\Filament\Tables\AssessmentColumns;
use App\Filament\Tables\AssessmentFilters;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/*
Assessments submitted by members of the public through the civilian
assessment on the public website (website/frontend, /assessment/).

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
    use AllowsOnlyListedActions;

    protected static ?string $model = PrivateAssessment::class;

    protected static ?string $slug = 'civilian-assessments';

    protected static ?string $title = 'Civilian Assessments';

    protected static ?string $navigationLabel = 'Civilian Assessments';

    protected static ?string $modelLabel = 'civilian assessment';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    // Sorts after the law-enforcement review page.
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    // View only: a danger assessment is a safety record, never edited or deleted.
    protected static function allowedActions(): array
    {
        return ['view'];
    }

    // The same rule as the PDF download (CivilianAssessmentPdfController), so
    // the screen and the route outside the panel can't disagree.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Filament::auth()->user());
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
        return AssessmentFilters::civilian(AssessmentColumns::cards($table)
            // The law-enforcement tables' columns, without an officer - see
            // AssessmentColumns. VictimSafePhoneNumber is deliberately absent
            // (see the class comment); it is in the detail view only.
            ->columns(AssessmentColumns::civilian())
            ->defaultSort('DateCreated', 'desc')
            // Without this the Yes-answers column would issue a query per row.
            // submitterInfo is deliberately not eager-loaded: nothing in the
            // table reads it, and the detail view loads one record anyway.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('assessmentAnswers'))
            // View only - no edit, no delete, no bulk actions. See the class
            // comment. Opening View and downloading the PDF both show the safe
            // phone number, so both are recorded (AssessmentAccessLog). Log,
            // as on Assessment review, lists the record's views and downloads
            // (admins only, like this screen) and records its own opening as
            // "Viewed Log", so it shows on the Logs page too.
            ->recordActions([
                ChangeHistory::action(withAccess: true),
                ViewAction::make()
                    ->modalHeading('View Civilian Assessment')
                    ->afterFormFilled(fn (PrivateAssessment $record) => AssessmentAccessLog::record(
                        $record,
                        AssessmentAccess::View,
                        Filament::auth()->user(),
                    ))
                    ->extraModalFooterActions(fn (PrivateAssessment $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.civilian-assessments.pdf', $record->DocumentID))
                            ->openUrlInNewTab(),
                    ]),
            ])
            ->emptyStateHeading('No civilian assessments yet')
            ->emptyStateDescription('Assessments submitted through the public civilian flow appear here.'),
            // The police tables' filter card (date submitted, victim and
            // offender names), plus this screen's own high-risk checkbox.
            [
                Filter::make('high_risk')
                    ->label('High risk ('.HighRisk::RULE.')')
                    // Counted in SQL here rather than in PHP, because a filter
                    // has to narrow the query itself, not the loaded page.
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'assessmentAnswers',
                        fn (Builder $inner): Builder => $inner->whereRaw(HighRisk::sql()),
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCivilianAssessments::route('/'),
        ];
    }
}
