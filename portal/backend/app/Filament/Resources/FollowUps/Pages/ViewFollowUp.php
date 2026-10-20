<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpStatus;
use App\Filament\FollowUps\FollowUpLog;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\AssessmentAccessLog;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Services\FollowUps;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
