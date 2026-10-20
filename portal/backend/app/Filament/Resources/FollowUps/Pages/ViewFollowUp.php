<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Filament\FollowUps\FollowUpLog;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\AssessmentAccessLog;
use App\Models\County;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\Resource as ResourceType;
use App\Models\ResourceRequestForm;
use App\Services\FollowUps;
use App\Support\PhoneNumber;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/*
One form's follow-up page (team plan task 16): the whole form, its follow-up
history, and the Update button - a status change with its note, or a note on
its own (App\Services\FollowUps enforces the rules). New-submission alerts
link here, so staff can read the form and start at once.

The record is looked up through FollowUpResource::getEloquentQuery(), scoped
by FollowUp::visibleTo(): a follow-up outside the user's kinds is a 404.
Opening an assessment's page shows the victim's safe phone number, so it is
recorded as a view in assessment_access_log (never in the follow-up Log).
Merely opening the page never changes the status.
*/
class ViewFollowUp extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FollowUpResource::class;

    protected string $view = 'filament.follow-ups.view';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(FollowUpResource::canView($this->getRecord()), 404);

        $form = $this->followUp()->form();

        if ($form instanceof LawEnforcementAssessment || $form instanceof PrivateAssessment) {
            AssessmentAccessLog::record($form, AssessmentAccess::View, Filament::auth()->user());
        }
    }

    public function followUp(): FollowUp
    {
        /** @var FollowUp */
        return $this->getRecord();
    }

    public function getTitle(): string | Htmlable
    {
        return $this->followUp()->formName() ?? 'Follow-up';
    }

    public function getSubheading(): string | Htmlable | null
    {
        $followUp = $this->followUp();

        return "{$followUp->Kind->label()} · {$followUp->Status->label()}";
    }

    public function getBreadcrumbs(): array
    {
        return [FollowUpResource::getUrl() => 'Follow-ups', 'Follow-up'];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->updateAction(),
            $this->newRequestAction(),

            // The plain form as a PDF: no follow-up or log information.
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): ?string => $this->pdfUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->pdfUrl() !== null),
        ];
    }

    private function updateAction(): Action
    {
        return Action::make('update')
            ->label('Update follow-up')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Update follow-up')
            ->modalDescription(fn (): string => 'Now: '.$this->followUp()->Status->label().'. Every update is kept with your name and the time.')
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(fn (): array => $this->updateStatusOptions())
                    ->default('keep')
                    ->selectablePlaceholder(false)
                    ->required(),
                Textarea::make('note')
                    ->label('Note')
                    ->helperText('Required for every update. New notes are added to the history; earlier ones are never changed.')
                    ->rows(4)
                    ->maxLength(FollowUps::NOTE_MAX)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Save update')
            ->action(function (array $data, Action $action): void {
                $status = $data['status'] === 'keep' ? null : FollowUpStatus::from($data['status']);

                try {
                    app(FollowUps::class)->update($this->followUp(), $status, $data['note'], Filament::auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $this->record->refresh();

                Notification::make()
                    ->title($status ? "Moved to {$status->label()}" : 'Note added')
                    ->success()
                    ->send();
            });
    }

    /*
    On a final form: a new resource request filled in from this one, every
    field editable (a new safe number, say), starting in Needs follow-up.
    From a resource request everything carries over; from an assessment
    (admins only) just the victim's name and safe phone - never offender
    details, answers or history. FollowUps::startResourceRequest() decides
    who may and saves it with the website's own rules.
    */
    private function newRequestAction(): Action
    {
        $label = fn (): string => $this->followUp()->Kind === FollowUpKind::ResourceRequest
            ? 'New request from this one'
            : 'New resource request for this victim';

        return Action::make('new_request')
            ->label($label)
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => FollowUps::canStartResourceRequest($this->followUp(), Filament::auth()->user()))
            ->modalHeading($label)
            ->modalDescription('A new resource request, filled in from this form. Change anything that is different now. It starts in Needs follow-up.')
            ->fillForm(fn (): array => $this->newRequestDefaults())
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('FirstName')->label('First name')->required()->maxLength(50),
                    TextInput::make('LastName')->label('Last name')->maxLength(50),
                    TextInput::make('EmailAddress')
                        ->label('Email')
                        ->email()
                        ->maxLength(250)
                        ->requiredWithout('SafePhoneNumber')
                        ->placeholder(null)
                        ->helperText('An email or a safe phone number: at least one is needed.'),
                    TextInput::make('SafePhoneNumber')
                        ->label('Safe phone number')
                        ->tel()
                        ->mask(PhoneNumber::MASK)
                        ->maxLength(20)
                        ->rule(PhoneNumber::rule())
                        ->requiredWithout('EmailAddress')
                        ->placeholder(null),
                ]),
                Select::make('CountyID')
                    ->label('County')
                    ->options(fn (): array => County::query()->orderBy('Name')->pluck('Name', 'id')->all()),
                CheckboxList::make('ResourceTypeIDs')
                    ->label('Resources of interest')
                    ->options(fn (): array => ResourceType::query()->orderBy('Name')->pluck('Name', 'id')->all())
                    ->columns(2),
                Textarea::make('Message')->label('Message')->rows(3)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Start request')
            ->action(function (array $data, Action $action): void {
                try {
                    $started = app(FollowUps::class)->startResourceRequest($this->followUp(), $data, Filament::auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Notification::make()->title('New resource request started')->success()->send();

                $this->redirect($started->url());
            });
    }

    /** @return array<string, mixed> */
    public function newRequestDefaults(): array
    {
        $form = $this->followUp()->form();

        return match (true) {
            $form instanceof ResourceRequestForm => [
                'FirstName' => $form->FirstName,
                'LastName' => $form->LastName,
                'EmailAddress' => $form->EmailAddress,
                'SafePhoneNumber' => $form->SafePhoneNumber,
                'CountyID' => $form->CountyID,
                'ResourceTypeIDs' => $form->resourceTypes->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                'Message' => $form->Message,
            ],
            // From an assessment: who to reach, nothing else.
            $form instanceof LawEnforcementAssessment, $form instanceof PrivateAssessment => [
                'FirstName' => $form->VictimFirstName,
                'LastName' => $form->VictimLastName,
                'SafePhoneNumber' => $form->VictimSafePhoneNumber,
            ],
            default => [],
        };
    }

    // For a request staff started: who started it, and - only for someone who
    // may open it - a link to the form it came from. A secretary never learns
    // that a request came from an assessment.
    /** @return array{by: ?string, from: ?array{label: string, url: string}}|null */
    public function startedFrom(): ?array
    {
        $form = $this->followUp()->form();

        if (! $form instanceof ResourceRequestForm || ! $form->isStaffEntry()) {
            return null;
        }

        $origin = $form->createdFrom();

        return [
            'by' => $form->enteredByName(),
            'from' => $origin && FollowUpResource::canView($origin)
                ? ['label' => $origin->Kind->label().': '.($origin->formName() ?? 'form'), 'url' => $origin->url()]
                : null,
        ];
    }

    // Requests started from this form, as links to their follow-up pages.
    /** @return list<array{label: string, url: string}> */
    public function requestsStartedHere(): array
    {
        $ids = ResourceRequestForm::query()
            ->where('CreatedFromFollowUpID', $this->followUp()->getKey())
            ->pluck('FormID');

        return FollowUp::query()
            ->whereIn('ResourceRequestFormID', $ids)
            ->orderBy('SubmittedAt')
            ->get()
            ->filter(fn (FollowUp $followUp): bool => FollowUpResource::canView($followUp))
            ->map(fn (FollowUp $followUp): array => [
                'label' => $followUp->SubmittedAt->copy()->setTimezone(Timezones::DISPLAY)->format('M j, Y').' · '.$followUp->Status->label(),
                'url' => $followUp->url(),
            ])
            ->values()
            ->all();
    }

    // "No change" keeps the status and adds a note; the rest are the moves
    // this form may make from where it is (FollowUpStatus::nextFor()).
    public function updateStatusOptions(): array
    {
        $followUp = $this->followUp();

        return ['keep' => "No change (stays {$followUp->Status->label()})"]
            + collect($followUp->Status->nextFor($followUp->Kind))
                ->mapWithKeys(fn (FollowUpStatus $status): array => [$status->value => $status->label()])
                ->all();
    }

    private function pdfUrl(): ?string
    {
        $form = $this->followUp()->form();

        return match (true) {
            $form instanceof LawEnforcementAssessment => route('staff.assessments.pdf', $form->DocumentID),
            $form instanceof PrivateAssessment => route('staff.civilian-assessments.pdf', $form->DocumentID),
            // Resource request PDFs come with part 5 of the follow-up build.
            default => null,
        };
    }

    /** @return list<array{when: string, who: ?string, heading: string, note: ?string}> */
    public function historySteps(): array
    {
        return FollowUpLog::steps($this->followUp());
    }
}
