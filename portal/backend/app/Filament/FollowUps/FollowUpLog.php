<?php

namespace App\Filament\FollowUps;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\FollowUp;
use App\Models\FollowUpEntry;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

/*
A form's follow-up history, from submission to now (team plan task 16): the
"Submitted" step, then every status change and note in the order they
happened, each with who and when. Status changes and notes only - views and
downloads are recorded in assessment_access_log and never shown here.

One Blade view renders it everywhere: the Log button on the Follow-ups queue
and the history on each form's follow-up page.

The notes can hold names, so opening the queue's pop-up is recorded as a view
of the form in assessment_access_log, like opening the follow-up page
(Finishing Touches item 28, AS 2026-10-27: opening it anywhere counts). An
assessment's view then shows on Logs; a resource request's is recorded but
not listed there.
*/
final class FollowUpLog
{
    public const VIEW = 'filament.follow-ups.history';

    // The queue's row button: the history in a pop-up.
    public static function action(): Action
    {
        return Action::make('log')
            ->label('Log')
            ->icon(Heroicon::OutlinedClock)
            ->modalHeading('Follow-Up Log')
            ->modalDescription(fn (FollowUp $record): string => $record->Kind->label().' · Current Status: '.$record->Status->label())
            // Once per open, after the row is found through the queue's
            // scoped query and before the pop-up is sent. mountUsing, as this
            // pop-up has no schema (see AssessmentViews). No catch: a failed
            // write stops it opening.
            ->mountUsing(function (FollowUp $record): void {
                $form = $record->form();

                if ($form !== null) {
                    AssessmentAccessLog::record($form, AssessmentAccess::View, Filament::auth()->user());
                }
            })
            ->modalContent(fn (FollowUp $record) => view(self::VIEW, ['steps' => self::steps($record)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * @return list<array{when: string, who: ?string, heading: string, note: ?string}>
     */
    public static function steps(FollowUp $followUp): array
    {
        $steps = [[
            'when' => self::when($followUp->SubmittedAt),
            'who' => null,
            'heading' => 'Submitted',
            'note' => null,
        ]];

        foreach ($followUp->entries()->with('enteredBy')->get() as $entry) {
            /** @var FollowUpEntry $entry */
            $steps[] = [
                'when' => self::when($entry->EnteredAt),
                'who' => $entry->enteredBy?->name ?? 'Unknown',
                'heading' => $entry->changedStatus()
                    ? "{$entry->FromStatus->label()} → {$entry->Status->label()}"
                    : "Note ({$entry->Status->label()})",
                'note' => $entry->Note,
            ];
        }

        return $steps;
    }

    // Stored UTC, shown Eastern - see App\Support\Timezones.
    private static function when($moment): string
    {
        return $moment?->copy()->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a') ?? '';
    }
}
