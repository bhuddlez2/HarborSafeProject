<?php

namespace App\Filament\Resources\AssessmentReview;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Assessments\AssessmentEditForm;
use App\Filament\Assessments\AssessmentViews;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Tables\AssessmentColumns;
use App\Filament\Tables\AssessmentFilters;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/*
Law-enforcement assessment submissions, for admins and police admins: admins
see all of them, a police admin only those submitted by members of their own
agency - its officers, and the police admin's own (section 5 rule 5 of Filament_CMS_Design.md). Both through
LawEnforcementAssessment::visibleTo() - see getEloquentQuery() below.

This page exists because section 5 grants admin "LE assessments: view" and
police_admin "own agency", and until now the only way an admin could see any
assessment was through the Officer Portal's own screens - which are officer
tools and no longer shown to admins.

View for admins; view and EDIT for police admins. Section 5 rule 1: an
assessment may be edited by its submitting officer or by an active police
admin of the submitter's agency; admin is view-only. So the one write action
here is the shared Edit (App\Filament\Assessments\AssessmentEditForm, the same
as My Assessments'), shown only where LawEnforcementAssessmentPolicy::update()
allows - a police admin on their agency's rows, including their own; never an
admin. No delete, no bulk action, no create - the wizard at
App\Filament\Pages\NewAssessment is the only way a row is ever written.

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

    // A police badge for law-enforcement submissions; resources/svg, registered
    // in AppServiceProvider (Heroicons has none).
    protected static string | BackedEnum | null $navigationIcon = 'hs-police-badge';

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
            UserRole::SuperAdmin,
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
            // See the class comment: Edit for police admins only; no delete
            // or bulk actions anywhere on this resource.
            ->recordActions([
                // Views, downloads and edits together (team plan task 22).
                ChangeHistory::action(withAccess: true),
                // Shared with My Assessments; logs each open.
                AssessmentViews::viewAction(),
                // Shared with My Assessments; shown only where the policy
                // allows (a police admin, own agency). Logs each open.
                AssessmentEditForm::action(),
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
