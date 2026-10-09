<?php

namespace App\Filament\Resources\AssessmentReview;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Tables\AssessmentColumns;
use App\Filament\Tables\AssessmentFilters;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/*
Law-enforcement assessment submissions, for admins and police admins: admins
see all of them, a police admin only those submitted by officers of their own
agency (section 5 rule 5 of Filament_CMS_Design.md). Both through
LawEnforcementAssessment::visibleTo() - see getEloquentQuery() below.

This page exists because section 5 grants admin "LE assessments: view" and
police_admin "own agency", and until now the only way an admin could see any
assessment was through the Officer Portal's own screens - which are officer
tools and no longer shown to admins.

VIEW-ONLY, WITHOUT EXCEPTION. Section 5 rule 1: only the submitting officer may
ever edit a submitted assessment, and police_admin and admin are view-only. So
there is no edit action, no delete action, no bulk action, and no create - the
wizard at App\Filament\Pages\NewAssessment is the only way a row is ever
written, and it is officers-only.

Deliberately NOT a replacement for the officer-facing My Assessments page
(App\Filament\Resources\LawEnforcementAssessments), which is officers only
and scoped to the signed-in officer. This one is the review view.

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

    // A police admin finds this in their single "Police Admin" group;
    // everyone else keeps the group and position above. Grouping and order
    // only - see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::ASSESSMENT_REVIEW : parent::getNavigationSort();
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::PoliceAdmin,
        );
    }

    /*
    The only thing keeping another agency's records out of a police admin's
    list: Filament does not run the policy per row. It also scopes the record
    lookup behind the View action, so another agency's record cannot be
    opened by key either. Never return an unscoped query here.
    */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Filament::auth()->user());
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
        return AssessmentFilters::apply($table
            // Shared with the other assessment table - see AssessmentColumns.
            ->columns(AssessmentColumns::for(reviewScreen: true))
            ->defaultSort('DateCreated', 'desc')
            // Without this the Yes-answers column would issue a query per row.
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter'])->withCount('edits'))
            ->stackedOnMobile()
            // View only - see the class comment. No edit, delete or bulk
            // actions anywhere on this resource.
            ->recordActions([
                ChangeHistory::action(),
                ViewAction::make()
                    ->extraModalFooterActions(fn (LawEnforcementAssessment $record): array => [
                        Action::make('download_pdf')
                            ->label('Download PDF')
                            ->url(route('staff.assessments.pdf', $record->DocumentID))
                            ->openUrlInNewTab(),
                    ]),
            ])
            ->emptyStateHeading('No assessments submitted yet')
            ->emptyStateDescription('Assessments submitted by officers through the portal appear here.'), reviewScreen: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessmentReview::route('/'),
        ];
    }

}
