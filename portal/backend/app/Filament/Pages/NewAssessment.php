<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAssessment;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

// The law-enforcement Lethality Assessment wizard, carried over from the
// former Next.js portal/frontend/app/police/portal/page.js: same phases,
// fields, labels, options, copy and styling. Field names and the baseline
// rules come from AssessmentController / LawEnforcementAssessmentController
// (the API's validation); the stricter client-side rules the original
// enforced (app/lib/validation.js) are layered on top.
class NewAssessment extends Page
{
    // Same wording and order as portal/frontend/app/lib/lethality-questions.js,
    // which the civilian flow still uses. Keys are RiskIndicator numbers.
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
    private const PRIMARY_BUTTON = 'bg-[#5C0F8B] text-white px-8 py-4 rounded-lg text-lg hover:bg-[#4C0B74] focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

    private const SECONDARY_BUTTON = 'border-2 border-[#5C0F8B] text-[#5C0F8B] px-8 py-4 rounded-lg text-lg hover:bg-[#5C0F8B] hover:text-white focus:outline-none focus:ring-4 focus:ring-[#5C0F8B]/40 transition';

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

    // Only officers submit assessments; police admins and admins view them.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::LawEnforcement);
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
                    ->extraAttributes(['class' => 'hs-lap-wizard bg-white rounded-2xl shadow-lg w-full px-8 md:px-12'])
                    // The questions phase had its own card padding and no
                    // Back/Continue row; everything else shared one layout.
                    ->extraAlpineAttributes([
                        // $refs isn't populated yet when Alpine first evaluates
                        // the root's own bindings, hence the guard.
                        'x-bind:class' => '($refs.stepsData ? getStepIndex(step) : 0) === '.self::QUESTIONS_STEP_INDEX
                            ." ? 'hs-lap-questions pt-10 pb-36 md:pb-14' : 'py-10 md:py-14'",
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
                TextInput::make('VictimSafePhoneNumber')
                    ->label($this->optionalLabel('Safe phone number'))
                    ->tel()
                    ->maxLength(20),
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
                    $this->dateOfBirthInput('OffenderDOB', $this->optionalLabel('Date of birth')),
                    $this->sexSelect('OffenderSex'),
                ]),
                // Optional in the API; the original wizard required it.
                TextInput::make('OffenderVictimRelationship')
                    ->label('Relationship to victim')
                    ->required()
                    ->markAsRequired(false)
                    ->maxLength(50)
                    ->validationMessages(['required' => 'Required']),
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
        return Step::make('Review & submit')
            ->schema([
                View::make('filament.pages.new-assessment.review')
                    ->viewData(fn (): array => [
                        'data' => $this->data,
                        'total' => count(self::QUESTIONS),
                        'submitError' => $this->submitError,
                    ]),
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
            ->markAsRequired(false)
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
            ->markAsRequired(false)
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
            ->markAsRequired(false)
            ->grow(false)
            ->extraAttributes(['class' => 'hs-lap-sex'])
            ->validationMessages([
                'required' => 'Select an option',
                'in' => 'Select an option',
            ]);
    }

    protected function optionalLabel(string $label): HtmlString
    {
        return new HtmlString(e($label).' <span class="text-gray-400 font-normal">(optional)</span>');
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
        $data = $this->form->getState();
        $this->submitError = null;

        try {
            DB::connection('Portal')->transaction(function () use ($data): void {
                $answers = AssessmentAnswers::create(collect($data)
                    ->only(array_map(fn (int $id): string => "RiskIndicator{$id}", array_keys(self::QUESTIONS)))
                    ->all());

                LawEnforcementAssessment::create([
                    ...collect($data)->only([
                        'OffenderFirstName',
                        'OffenderLastName',
                        'OffenderSex',
                        'OffenderDOB',
                        'OffenderVictimRelationship',
                        'VictimFirstName',
                        'VictimLastName',
                        'VictimSex',
                        'VictimDOB',
                        'VictimSafePhoneNumber',
                    ])->all(),
                    'AssessmentDocID' => $answers->getKey(),
                    // Always the signed-in officer, never anything client-supplied.
                    'submitted_by' => Filament::auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->submitError = 'The assessment could not be saved. Please try again.';

            return;
        }

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
