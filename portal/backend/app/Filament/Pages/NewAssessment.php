<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Assessments\AssessmentInputs;
use App\Filament\Assessments\RelationshipFields;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Forms\RequirementMarkers;
use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAssessment;
use App\Enums\FollowUpKind;
use App\Enums\NewSubmission;
use App\Services\FollowUps;
use App\Services\SubmissionAlerts;
use App\Support\AssessmentDraft;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

// The law-enforcement Lethality Assessment wizard, carried over from the
// former Next.js portal/frontend/app/police/portal/page.js: same phases,
// fields, labels, options, copy and styling. Field names and the baseline
// rules came from the old officer API (removed in team plan task 29), with
// the stricter client-side rules the original enforced layered on top. This
// wizard is now the only way an officer assessment is created.
class NewAssessment extends Page
{
    // Same wording and order as website/frontend/src/app/lib/lethality-questions.js,
    // which the civilian assessment uses. Keys are RiskIndicator numbers.
    public const QUESTIONS = [
        1 => 'Have they ever used a weapon against you or threatened you with a weapon?',
        2 => 'Have they threatened to kill you or your children?',
        3 => 'Have they ever tried to choke (strangle) you?',
        4 => 'Do they have a gun or can they get one easily?',
        5 => 'Do they have an active order of protection against them?',
        6 => 'Are they a sex offender?',
        7 => 'Do you have injuries from previous incidents?',
        8 => 'Are they a serial abuser?',
        9 => 'Are they affiliated with gangs or criminal organizations?',
        10 => 'Do they have any involvement in human trafficking?',
        11 => 'Are there children involved who are at risk?',
    ];

    /*
    The question as the officer reads it aloud: the offender's first name in
    place of "they", and "you" kept, since the victim is the one listening.
    Written out per question, word for word the website's wording for a report
    for oneself (personalTemplates in lethality-questions.js); change both
    together. A blank first name, or one of only spaces, gives the generic
    text. Display only: answers are stored by number, and the View pop-up,
    record views and PDFs keep QUESTIONS. Plain text, printed escaped.
    */
    public static function questionText(int $id, ?string $offenderFirstName = null): string
    {
        $offender = Str::trim((string) $offenderFirstName);

        if ($offender === '') {
            return self::QUESTIONS[$id];
        }

        return match ($id) {
            1 => "Has {$offender} ever used a weapon against you or threatened you with a weapon?",
            2 => "Has {$offender} threatened to kill you or your children?",
            3 => "Has {$offender} ever tried to choke (strangle) you?",
            4 => "Does {$offender} have a gun or can {$offender} get one easily?",
            5 => "Is there an active order of protection against {$offender}?",
            6 => "Is {$offender} a sex offender?",
            8 => "Is {$offender} a serial abuser?",
            9 => "Is {$offender} affiliated with gangs or criminal organizations?",
            10 => "Does {$offender} have any involvement in human trafficking?",
            // 7 and 11 name no offender.
            default => self::QUESTIONS[$id],
        };
    }

    // Wizard step order; the questions step's index is what the view and the
    // theme key off (see resources/css/filament/staff/theme.css). The review's
    // Edit and Change buttons jump to the victim, offender and questions steps.
    private const VICTIM_STEP_INDEX = 1;

    private const OFFENDER_STEP_INDEX = 2;

    private const QUESTIONS_STEP_INDEX = 4;

    private const REVIEW_STEP_INDEX = 5;

    // Tailwind classes lifted from the original buttons.
    // Narrower side padding on phones so Back and Continue fit side by side
    // at 375 px (team plan task 10).
    private const PRIMARY_BUTTON = 'bg-[#5C0F8B] text-white px-5 md:px-8 py-4 rounded-lg text-lg hover:bg-[#4C0B74] focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

    private const SECONDARY_BUTTON = 'border-2 border-[#5C0F8B] text-[#5C0F8B] px-5 md:px-8 py-4 rounded-lg text-lg hover:bg-[#5C0F8B] hover:text-white focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

    protected string $view = 'filament.pages.new-assessment';

    protected static ?string $slug = 'police/new-assessment';

    protected static ?string $title = 'New Assessment';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 3;

    protected Width | string | null $maxContentWidth = Width::Full;

    public ?array $data = [];

    public int $questionIndex = 0;

    // Set once the last question is answered. From then on every step's
    // Continue, and every answer, goes straight back to Review & Submit, so a
    // fix made from the review doesn't mean walking through the whole form.
    public bool $reviewReached = false;

    public bool $submitted = false;

    public ?string $submitError = null;

    // Officers, and police admins with an agency, submit their own
    // assessments through this same wizard (section 5 rule 1). Admins,
    // secretaries and a police admin with no agency never open it.
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->hasActiveRole(UserRole::LawEnforcement)
            || $user?->managedAgencyId() !== null;
    }

    // A police admin finds this in their single "Police Admin" group;
    // officers keep the group and position above. Grouping and order only -
    // see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::NEW_ASSESSMENT : parent::getNavigationSort();
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    $this->backToReview($this->officerStep()),
                    $this->backToReview($this->victimStep()),
                    $this->backToReview($this->offenderStep()),
                    $this->backToReview($this->introStep()),
                    $this->questionsStep(),
                    $this->reviewStep(),
                ])
                    ->hiddenHeader()
                    ->contained(false)
                    ->alpineSubmitHandler('$wire.create()')
                    ->extraAttributes(['class' => 'hs-lap-wizard bg-white rounded-2xl shadow-lg w-full px-5 md:px-12'])
                    // The questions phase had its own card padding and no
                    // Back/Continue row; everything else shared one layout.
                    ->extraAlpineAttributes([
                        // $refs isn't populated yet when Alpine first evaluates
                        // the root's own bindings, hence the guard.
                        'x-bind:class' => '($refs.stepsData ? getStepIndex(step) : 0) === '.self::QUESTIONS_STEP_INDEX
                            ." ? 'hs-lap-questions pt-8 pb-36 md:pt-10 md:pb-14' : 'py-8 md:py-14'",
                    ])
                    ->previousAction(fn (Action $action): Action => $action
                        ->label('Back')
                        ->extraAttributes(['class' => self::SECONDARY_BUTTON]))
                    ->nextAction(fn (Action $action): Action => $action
                        ->label('Continue')
                        ->extraAttributes([
                            'class' => self::PRIMARY_BUTTON,
                            // The intro phase's button read "Begin assessment";
                            // once the review has been reached, every step's
                            // button goes back to it (backToReview()).
                            'x-text' => "\$wire.reviewReached ? 'Return to review' : ((\$refs.stepsData ? getStepIndex(step) : 0) === 3 ? 'Begin assessment' : 'Continue')",
                        ]))
                    ->submitAction(new HtmlString(view('filament.pages.new-assessment.submit-button', [
                        'class' => self::PRIMARY_BUTTON,
                    ])->render())),
            ])
            ->statePath('data');
    }

    /*
    Once the review has been reached, a step's Continue (or Enter) goes straight
    back to Review & Submit instead of on to the next step. The step is checked
    first, exactly as Continue checks it - this runs after its validation - and
    then, instead of letting the wizard move one step on, it sends the browser
    the wizard's own "next step" event pointing at the review and stops
    (Halt), so the usual one-step move never happens.
    */
    protected function backToReview(Step $step): Step
    {
        return $step->afterValidation(function (Step $component): void {
            if (! $this->reviewReached) {
                return;
            }

            $steps = array_values(array_filter(
                $component->getContainer()->getComponents(),
                fn ($child): bool => $child instanceof Step,
            ));

            $this->dispatch(
                'next-wizard-step',
                key: $component->getContainer()->getParentComponent()->getKey(),
                step: $steps[self::REVIEW_STEP_INDEX]->getKey(),
                currentStep: $component->getKey(),
            );

            throw new Halt;
        });
    }

    protected function officerStep(): Step
    {
        return Step::make('Submitting Officer')
            ->schema([
                View::make('filament.pages.new-assessment.officer')
                    ->viewData(fn (): array => ['officer' => Filament::auth()->user()->officerIdentity()]),
            ]);
    }

    protected function victimStep(): Step
    {
        return Step::make('About the Victim')
            ->schema([
                $this->heading('About the Victim'),
                Grid::make(2)->schema([
                    $this->nameInput('VictimFirstName', 'First name'),
                    $this->nameInput('VictimLastName', 'Last name'),
                ]),
                Flex::make([
                    $this->dateOfBirthInput('VictimDOB', 'Date of birth', required: true),
                    $this->sexSelect('VictimSex'),
                ]),
                // Required so the organisation can follow up with the victim.
                // Confirmed as safe on the review (reviewStep()).
                AssessmentInputs::safePhone('Safe phone number'),
            ]);
    }

    protected function offenderStep(): Step
    {
        return Step::make('About the Offender')
            ->schema([
                $this->heading('About the Offender'),
                Grid::make(2)->schema([
                    $this->nameInput('OffenderFirstName', 'First name'),
                    $this->nameInput('OffenderLastName', 'Last name'),
                ]),
                Flex::make([
                    $this->dateOfBirthInput('OffenderDOB', RequirementMarkers::optionalLabel('Date of birth'), required: false),
                    $this->sexSelect('OffenderSex'),
                ]),
                // The shared dropdown + "Please specify" for Other.
                ...RelationshipFields::make(),
            ]);
    }

    protected function introStep(): Step
    {
        return Step::make('Lethality Assessment')
            ->schema([
                View::make('filament.pages.new-assessment.intro')
                    ->viewData(['total' => count(self::QUESTIONS)]),
            ]);
    }

    protected function questionsStep(): Step
    {
        $answers = collect(array_keys(self::QUESTIONS))
            ->map(fn (int $id): Hidden => Hidden::make("RiskIndicator{$id}")
                ->required()
                ->rules(['boolean']))
            ->all();

        return Step::make('Questions')
            ->schema([
                ...$answers,
                View::make('filament.pages.new-assessment.questions')
                    ->viewData(fn (): array => [
                        'questions' => $this->askedQuestions(),
                        'index' => $this->questionIndex,
                    ]),
            ]);
    }

    protected function reviewStep(): Step
    {
        return Step::make('Review & Submit')
            ->schema([
                View::make('filament.pages.new-assessment.review')
                    ->viewData(fn (): array => [
                        'data' => $this->data,
                        'questions' => $this->askedQuestions(),
                        'submitError' => $this->submitError,
                        // Where the Edit and Change buttons go.
                        'victimStep' => self::VICTIM_STEP_INDEX,
                        'offenderStep' => self::OFFENDER_STEP_INDEX,
                        'questionsStep' => self::QUESTIONS_STEP_INDEX,
                    ]),

                // Required by validation in create(), not only by the
                // disabled button. The civilian assessment makes the same
                // check in its own wording.
                AssessmentInputs::safePhoneConfirmation(),
            ]);
    }

    // The 11 questions as this form asks them (questionText()), keyed by
    // RiskIndicator number like QUESTIONS. Read on every render, so a changed
    // offender name shows on the next step.
    protected function askedQuestions(): array
    {
        $first = $this->data['OffenderFirstName'] ?? null;

        return collect(self::QUESTIONS)
            ->mapWithKeys(fn (string $text, int $id): array => [$id => static::questionText($id, is_string($first) ? $first : null)])
            ->all();
    }

    protected function heading(string $text): View
    {
        return View::make('filament.pages.new-assessment.heading')
            ->viewData(['text' => $text]);
    }

    // The field rules are AssessmentInputs', shared with the Edit pop-up;
    // only the wizard's look is added here.
    protected function nameInput(string $name, string $label): TextInput
    {
        return AssessmentInputs::name($name, $label);
    }

    protected function dateOfBirthInput(string $name, string | Htmlable $label, bool $required): DatePicker
    {
        return AssessmentInputs::dateOfBirth($name, $label, $required)
            ->grow(false);
    }

    protected function sexSelect(string $name): Select
    {
        return AssessmentInputs::sex($name)
            ->placeholder('Select')
            ->selectablePlaceholder()
            ->grow(false)
            ->extraAttributes(['class' => 'hs-lap-sex']);
    }

    // Records the current question's answer and moves on. Returns true once
    // the last question is answered - or any question once the review has been
    // reached, since that answer was changed from it - so the view advances the
    // wizard to Review & Submit.
    public function answerQuestion(bool $answer): bool
    {
        $ids = array_keys(self::QUESTIONS);
        $this->data['RiskIndicator'.$ids[$this->questionIndex]] = $answer;

        if (! $this->reviewReached && $this->questionIndex < count($ids) - 1) {
            $this->questionIndex++;

            return false;
        }

        $this->reviewReached = true;

        return true;
    }

    // A Change button on the review: show that one question again.
    public function editAnswer(int $index): void
    {
        if ($index >= 0 && $index < count(self::QUESTIONS)) {
            $this->questionIndex = $index;
        }
    }

    public function previousQuestion(): void
    {
        if ($this->questionIndex > 0) {
            $this->questionIndex--;
        }
    }

    /*
    Resume: puts a form saved in this browser tab back into the wizard (see
    App\Support\AssessmentDraft) and returns the step to reopen on, or null
    when there is nothing to restore. Only the wizard's own fields come back;
    the safe-phone confirmation is asked for again, and Submit validates
    everything as usual, so a tampered draft can't skip a rule.
    */
    public function restoreDraft(mixed $draft): ?int
    {
        abort_unless(static::canAccess(), 403);

        if ($this->submitted || ! is_array($draft)) {
            return null;
        }

        $data = AssessmentDraft::cleanData($draft['data'] ?? null);

        if ($data === []) {
            return null;
        }

        $this->form->fill($data);
        $this->submitError = null;

        $step = max(0, min(self::REVIEW_STEP_INDEX, (int) ($draft['step'] ?? 0)));
        $allAnswered = AssessmentDraft::allAnswered($data);

        // The review only once every question has an answer.
        if ($step === self::REVIEW_STEP_INDEX && ! $allAnswered) {
            $step = self::QUESTIONS_STEP_INDEX;
        }

        $this->reviewReached = $allAnswered
            && ((bool) ($draft['reviewReached'] ?? false) || $step === self::REVIEW_STEP_INDEX);
        $this->questionIndex = max(0, min(count(self::QUESTIONS) - 1, (int) ($draft['questionIndex'] ?? 0)));

        return $step;
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $this->submitError = null;

        try {
            $assessment = DB::connection('Portal')->transaction(function () use ($data): LawEnforcementAssessment {
                $answers = AssessmentAnswers::create(collect($data)
                    ->only(array_map(fn (int $id): string => "RiskIndicator{$id}", array_keys(self::QUESTIONS)))
                    ->all());

                return LawEnforcementAssessment::create([
                    ...collect($data)->only([
                        'OffenderFirstName',
                        'OffenderLastName',
                        'OffenderSex',
                        'OffenderDOB',
                        'OffenderVictimRelationship',
                        'OffenderVictimRelationshipOther',
                        'VictimFirstName',
                        'VictimLastName',
                        'VictimSex',
                        'VictimDOB',
                        'VictimSafePhoneNumber',
                    ])->all(),
                    'AssessmentDocID' => $answers->getKey(),
                    // Always the signed-in user - the officer, or the police
                    // admin submitting their own - never anything client-supplied.
                    'submitted_by' => Filament::auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->submitError = 'The assessment could not be submitted. Nothing was saved. Your answers are still here. Please try again.';

            return;
        }

        // Harbor Safe follows up every officer assessment, and its admins hear
        // about each one in their bell. None of this is ever shown to the
        // officer or their police admin.
        $followUp = app(FollowUps::class)->open(FollowUpKind::LawEnforcementAssessment, $assessment->DocumentID);
        app(SubmissionAlerts::class)->raise(NewSubmission::LawEnforcementAssessment, $followUp);

        // The form saved in this tab (AssessmentDraft) has done its job.
        $this->dispatch('hs-assessment-draft-clear');

        Notification::make()
            ->title('Assessment submitted')
            ->success()
            ->send();

        $this->submitted = true;
    }

    public function startNewAssessment(): void
    {
        $this->form->fill();
        $this->questionIndex = 0;
        $this->reviewReached = false;
        $this->submitError = null;
        $this->submitted = false;
    }
}
