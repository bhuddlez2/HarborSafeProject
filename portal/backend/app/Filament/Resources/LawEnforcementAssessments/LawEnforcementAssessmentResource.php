<?php

namespace App\Filament\Resources\LawEnforcementAssessments;

use App\Enums\UserRole;
use App\Filament\Assessments\AssessmentEditForm;
use App\Filament\Assessments\AssessmentViews;
use App\Filament\Assessments\ChangeHistory;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Tables\AssessmentColumns;
use App\Filament\Tables\AssessmentFilters;
use App\Models\LawEnforcementAssessment;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

// "My Assessments": list, view and edit of law-enforcement assessments, for
// officers only, each seeing their own submissions. Police admins and admins
// review submissions in AssessmentReviewResource instead (admin all, police
// admin own agency). Access is canAccess() below plus the
// LawEnforcementAssessment::visibleTo() query scope, with
// LawEnforcementAssessmentPolicy authorizing each record. Edit (shared with
// Assessment review, App\Filament\Assessments\AssessmentEditForm) is the
// submitting officer's here, needs a reason, and goes through
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

    // The edit pop-up's fields, shared with Assessment review's Edit - see
    // App\Filament\Assessments\AssessmentEditForm.
    public static function form(Schema $schema): Schema
    {
        return AssessmentEditForm::schema($schema);
    }

    public static function table(Table $table): Table
    {
        return AssessmentFilters::apply($table
            // Shared with the other assessment table - see AssessmentColumns.
            ->columns(AssessmentColumns::for(reviewScreen: false))
            ->defaultSort('DateCreated', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['assessmentAnswers', 'submitter'])->withCount('edits'))
            ->stackedOnMobile()
            ->emptyStateHeading('No assessments yet')
            ->emptyStateDescription('Completed LAP screenings will appear here. Use the home page to start one.')
            ->recordActions([
                ChangeHistory::action(),
                // Shared with Assessment review; logs each open.
                AssessmentViews::viewAction(),
                // Shared with Assessment review: policy-gated, reason
                // required, saved through AssessmentEditor, logs each open.
                AssessmentEditForm::action(),
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

}
