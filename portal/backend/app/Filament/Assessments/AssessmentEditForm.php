<?php

namespace App\Filament\Assessments;

use App\Filament\Pages\NewAssessment;
use App\Models\LawEnforcementAssessment;
use App\Services\AssessmentEditor;
use App\Support\PhoneNumber;
use App\Support\Timezones;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/*
The one Edit pop-up for a law-enforcement assessment, used by both tables:
My Assessments (the submitting officer) and Assessment review (an active
police admin of the submitter's agency, on their officers' records and their
own). One definition, so the two can't drift apart.

Who may edit is LawEnforcementAssessmentPolicy::update() (section 5 rule 1):
checked to show the button (authorize) and again on the server at save. Admin
is view-only. Every save needs a reason and goes through
App\Services\AssessmentEditor, which writes the change log with the signed-in
user as ChangedBy - the models refuse any other update. Opening the pop-up
shows the whole record, so it logs a view (AssessmentViews::log(), failing
closed).
*/
final class AssessmentEditForm
{
    // Details and answers are saved by AssessmentEditor, not by Filament
    // directly - see action(). Styled to match the View pop-up: purple
    // header, light-purple cards (the hs-edit-* rules in theme.css).
    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.forms.assessment-edit-header')
                    ->columnSpanFull()
                    ->viewData(fn (?LawEnforcementAssessment $record): array => [
                        'record' => $record,
                        'submitted' => $record?->DateCreated
                            ? Carbon::parse($record->DateCreated)->setTimezone(Timezones::DISPLAY)->format('M j, Y \a\t g:i a')
                            : null,
                    ]),

                Section::make('Victim')
                    ->extraAttributes(['class' => 'hs-edit-card'])
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        // Every field's rules are the wizard's (AssessmentInputs),
                        // so an edit is held to the standard of the original
                        // submission.
                        AssessmentInputs::name('VictimFirstName', 'First name'),
                        AssessmentInputs::name('VictimLastName', 'Last name'),
                        AssessmentInputs::sex('VictimSex'),
                        AssessmentInputs::dateOfBirth('VictimDOB', 'Date of birth', required: true),
                        AssessmentInputs::safePhone('Safe contact number')
                            ->helperText('The number the victim said is safe to call. Other numbers may not be.')
                            ->columnSpanFull(),
                        // As in the wizard, a new number must be confirmed as
                        // safe. Only asked when the number is changed.
                        AssessmentInputs::safePhoneConfirmation()
                            ->visible(fn (Get $get, ?LawEnforcementAssessment $record): bool => PhoneNumber::format($get('VictimSafePhoneNumber'))
                                !== PhoneNumber::format($record?->VictimSafePhoneNumber))
                            ->columnSpanFull(),
                    ]),

                Section::make('Offender')
                    ->extraAttributes(['class' => 'hs-edit-card'])
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        AssessmentInputs::name('OffenderFirstName', 'First name'),
                        AssessmentInputs::name('OffenderLastName', 'Last name'),
                        AssessmentInputs::sex('OffenderSex'),
                        AssessmentInputs::dateOfBirth('OffenderDOB', 'Date of birth', required: false),
                        // Same dropdown + "Please specify" as the wizard.
                        ...RelationshipFields::make(),
                    ]),

                // Numbered rows with Yes / No, like the View pop-up's checklist.
                Section::make('Risk Indicators')
                    ->extraAttributes(['class' => 'hs-edit-card hs-edit-risk'])
                    ->columnSpanFull()
                    ->schema(
                        collect(NewAssessment::QUESTIONS)
                            ->map(fn (string $question, int $id): ToggleButtons => ToggleButtons::make("RiskIndicator{$id}")
                                ->label($id.'. '.$question)
                                ->boolean('Yes', 'No')
                                // Every question answered, as in the wizard.
                                ->required()
                                ->colors([1 => 'danger', 0 => 'gray'])
                                ->icons([1 => null, 0 => null])
                                ->inline()
                                ->grouped()
                                ->inlineLabel())
                            ->values()
                            ->all(),
                    ),

                Section::make('Reason for Change')
                    ->extraAttributes(['class' => 'hs-edit-card hs-edit-reason'])
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('reason')
                            ->hiddenLabel()
                            ->placeholder("e.g. Corrected the spelling of the victim's last name")
                            ->helperText('Required. Recorded with this edit in the change log, which cannot be altered.')
                            ->required()
                            ->maxLength(255)
                            ->rows(2),
                    ]),
            ]);
    }

    public static function action(): EditAction
    {
        return EditAction::make()
            // Section 5 rule 1, through LawEnforcementAssessmentPolicy::update():
            // the submitting officer, or a police admin of the submitter's
            // agency. Hidden and refused for everyone else, admins included.
            ->authorize(fn (LawEnforcementAssessment $record): bool => Gate::allows('update', $record))
            ->modalHeading('Edit Assessment')
            // The pop-up shows the whole record, so opening it is a view.
            ->afterFormFilled(fn (LawEnforcementAssessment $record) => AssessmentViews::log($record))
            // A panel from the side rather than a centred pop-up: full-height
            // and scrollable one-handed on a phone (team plan task 10).
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Save edit')
            // The answers live on _assessment_answers, not this record,
            // and the reason always starts empty.
            ->mutateRecordDataUsing(function (array $data, LawEnforcementAssessment $record): array {
                foreach (AssessmentEditor::answerFields() as $field) {
                    $data[$field] = (bool) $record->assessmentAnswers?->{$field};
                }

                return [...$data, 'reason' => null];
            })
            ->using(function (LawEnforcementAssessment $record, array $data, EditAction $action): LawEnforcementAssessment {
                // Checked again on the server at save, so a forced Livewire
                // call is refused even when the button is hidden.
                Gate::authorize('update', $record);

                $edit = AssessmentEditor::apply($record, $data, $data, $data['reason'], Filament::auth()->user());

                if ($edit === null) {
                    Notification::make()
                        ->title('No changes to save')
                        ->body('Nothing in the assessment was different, so no edit was recorded.')
                        ->info()
                        ->send();

                    $action->halt();
                }

                return $record;
            })
            ->schema(fn (Schema $schema): Schema => self::schema($schema))
            ->successNotificationTitle('Edit saved and recorded in the change history');
    }
}
