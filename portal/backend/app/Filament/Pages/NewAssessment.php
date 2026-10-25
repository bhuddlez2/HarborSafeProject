<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Assessments\RelationshipFields;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Forms\RequirementMarkers;
use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAssessment;
use App\Enums\FollowUpKind;
use App\Enums\NewSubmission;
use App\Services\FollowUps;
use App\Services\SubmissionAlerts;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
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
        5 => 'Do they have an active order or protection against them?',
        6 => 'Are they a sex offender?',
        7 => 'Do you have injuries from previous incidents?',
        8 => 'Are they a serial abuser?',
        9 => 'Are they affiliated with gangs or criminal organizations?',
        10 => 'Do they have any involvement in human trafficking?',
        11 => 'Are there children involved who are at risk?',
    ];

    // Wizard step order; the questions step's index is what the view and the
    // theme key off (see resources/css/filament/staff/theme.css).
    private const QUESTIONS_STEP_INDEX = 4;

    private const NAME_PATTERN = "/^[a-zA-Z\s'-]+$/";

    // Tailwind classes lifted from the original buttons.
    // Narrower side padding on phones so Back and Continue fit side by side
    // at 375 px (team plan task 10).
    private const PRIMARY_BUTTON = 'bg-[#5C0F8B] text-white px-5 md:px-8 py-4 rounded-lg text-lg hover:bg-[#4C0B74] focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

    private const SECONDARY_BUTTON = 'border-2 border-[#5C0F8B] text-[#5C0F8B] px-5 md:px-8 py-4 rounded-lg text-lg hover:bg-[#5C0F8B] hover:text-white focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

    protected string $view = 'filament.pages.new-assessment';

    protected static ?string $slug = 'police/new-assessment';

    protected static ?string $title = 'New assessment';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string | UnitEnum | null $navigationGroup = 'Officer Portal';

    protected static ?int $navigationSort = 3;

    protected Width | string | null $maxContentWidth = Width::Full;

    public ?array $data = [];

    public int $questionIndex = 0;

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
                    $this->officerStep(),
                    $this->victimStep(),
                    $this->offenderStep(),
                    $this->introStep(),
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
                            // The intro phase's button read "Begin assessment".
                            'x-text' => "(\$refs.stepsData ? getStepIndex(step) : 0) === 3 ? 'Begin assessment' : 'Continue'",
                        ]))
                    ->submitAction(new HtmlString(view('filament.pages.new-assessment.submit-button', [
                        'class' => self::PRIMARY_BUTTON,
                    ])->render())),
            ])
            ->statePath('data');
    }

    protected function officerStep(): Step
    {
        return Step::make('Submitting officer')
            ->schema([
                View::make('filament.pages.new-assessment.officer')
                    ->viewData(fn (): array => ['officer' => Filament::auth()->user()->officerIdentity()]),
            ]);
    }

    protected function victimStep(): Step
    {
        return Step::make('About the victim')
            ->schema([
                $this->heading('About the victim'),
                Grid::make(2)->schema([
                    $this->nameInput('VictimFirstName', 'First name'),
                    $this->nameInput('VictimLastName', 'Last name'),
                ]),
                Flex::make([
                    $this->dateOfBirthInput('VictimDOB', 'Date of birth')
                        ->required(),
                    $this->sexSelect('VictimSex'),
                ]),
                // Required so the organisation can follow up with the victim.
                // A full 10-digit number, shown as (xxx)xxx-xxxx while typing.
                TextInput::make('VictimSafePhoneNumber')
                    ->label('Safe phone number')
                    ->tel()
                    ->mask(PhoneNumber::MASK)
                    ->required()
                    ->maxLength(20)
                    ->rule(PhoneNumber::rule())
                    // A confirmation given for one number never carries over
                    // to another (see reviewStep()).
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set) => $set('SafePhoneConfirmed', false))
                    ->validationMessages(['required' => 'Required']),
            ]);
    }

    protected function offenderStep(): Step
    {
        return Step::make('About the offender')
            ->schema([
                $this->heading('About the offender'),
                Grid::make(2)->schema([
                    $this->nameInput('OffenderFirstName', 'First name'),
                    $this->nameInput('OffenderLastName', 'Last name'),
                ]),
                Flex::make([
                    $this->dateOfBirthInput('OffenderDOB', RequirementMarkers::optionalLabel('Date of birth')),
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
                        'questions' => self::QUESTIONS,
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
                        'questions' => self::QUESTIONS,
                        'submitError' => $this->submitError,
                    ]),

                /*
                A second check that the safe number really is safe, required
                by validation in create(), not only by the disabled button.
                Unticked whenever the number changes. Never stored: it only
                makes sure the officer knows the number must be safe to call.
                The civilian assessment makes the same check in its own wording.
                */
                Checkbox::make('SafePhoneConfirmed')
                    ->label(fn (Get $get): string => 'I have confirmed that '
                        .(filled($get('VictimSafePhoneNumber')) ? $get('VictimSafePhoneNumber') : 'the safe phone number')
                        .' is safe to use to contact the victim.')
                    ->accepted()
                    ->live()
                    ->dehydrated(false)
                    ->validationMessages(['accepted' => 'Confirm the safe phone number before submitting.']),
            ]);
    }

    protected function heading(string $text): View
    {
        return View::make('filament.pages.new-assessment.heading')
            ->viewData(['text' => $text]);
    }

    protected function nameInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(50)
            ->regex(self::NAME_PATTERN)
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : $state)
            ->validationMessages([
                'required' => 'Required',
                'regex' => 'Cannot contain numbers or symbols',
            ]);
    }

    protected function dateOfBirthInput(string $name, string | Htmlable $label): DatePicker
    {
        return DatePicker::make($name)
            ->label($label)
            ->native()
            ->maxDate(now())
            ->grow(false)
            ->validationMessages([
                'required' => 'Required',
                'date' => 'Enter a valid date',
                'before_or_equal' => 'Date cannot be in the future',
            ]);
    }

    protected function sexSelect(string $name): Select
    {
        return Select::make($name)
            ->label('Sex')
            ->native()
            ->placeholder('Select')
            ->selectablePlaceholder()
            ->options([
                'M' => 'Male',
                'F' => 'Female',
                'O' => 'Other',
            ])
            ->required()
            ->grow(false)
            ->extraAttributes(['class' => 'hs-lap-sex'])
            ->validationMessages([
                'required' => 'Select an option',
                'in' => 'Select an option',
            ]);
    }

    // Records the current question's answer and moves on. Returns true once
    // the last question is answered, so the view can advance the wizard.
    public function answerQuestion(bool $answer): bool
    {
        $ids = array_keys(self::QUESTIONS);
        $this->data['RiskIndicator'.$ids[$this->questionIndex]] = $answer;

        if ($this->questionIndex < count($ids) - 1) {
            $this->questionIndex++;

            return false;
        }

        return true;
    }

    public function previousQuestion(): void
    {
        if ($this->questionIndex > 0) {
            $this->questionIndex--;
        }
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
            $this->submitError = 'The assessment could not be saved. Please try again.';

            return;
        }

        // Harbor Safe follows up every officer assessment, and its admins hear
        // about each one in their bell. None of this is ever shown to the
        // officer or their police admin.
        $followUp = app(FollowUps::class)->open(FollowUpKind::LawEnforcementAssessment, $assessment->DocumentID);
        app(SubmissionAlerts::class)->raise(NewSubmission::LawEnforcementAssessment, $followUp);

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
        $this->submitError = null;
        $this->submitted = false;
    }
}
