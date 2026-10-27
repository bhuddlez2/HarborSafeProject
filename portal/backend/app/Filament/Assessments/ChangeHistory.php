<?php

namespace App\Filament\Assessments;

use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Models\LogEntry;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Infolists\Components\ViewEntry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/*
The change history of a law-enforcement assessment, shared by every screen
that shows one: a "Change log" button beside View on My Assessments and
Assessment review, and an edit row's View on the Logs page (one edit at a
time). One Blade
view renders all of them, so an edit reads the same wherever it is opened.

Deliberately NOT part of the View pop-up: View shows the record as it is now,
complete on its own, so that an export of it is the whole current document -
not a document with its history interleaved, or one that looks like something
was left out.

Nothing here decides who may see a record - callers' queries are already
scoped through LawEnforcementAssessment::visibleTo().
*/
final class ChangeHistory
{
    public const VIEW = 'filament.infolists.change-history';

    // The "Change log" row button: every edit since submission, newest first,
    // in its own pop-up. Purple on an edited record, so edited ones stand out
    // in the list; light gray and disabled on one never edited (the gray is
    // theme.css's .fi-disabled rule - the theme would otherwise paint it
    // purple like every row button). Needs ->withCount('edits') on the table
    // query; place it before ViewAction.
    public static function action(): Action
    {
        return Action::make('changeLog')
            ->label('Change log')
            ->icon(Heroicon::OutlinedClock)
            ->color(fn (LawEnforcementAssessment $record): string => $record->edits_count ? 'primary' : 'gray')
            ->disabled(fn (LawEnforcementAssessment $record): bool => ! $record->edits_count)
            ->tooltip(fn (LawEnforcementAssessment $record): string => $record->edits_count
                ? 'Every edit since submission'
                : 'No edits since submission')
            ->modalHeading('Change log')
            // Old and new values are the record's contents, so opening this
            // is a view. mountUsing, not afterFormFilled: this pop-up has no
            // schema, and Filament only calls afterFormFilled when there is
            // one. Not in modalContent(), which re-runs on every render.
            ->mountUsing(fn (LawEnforcementAssessment $record) => AssessmentViews::log($record))
            ->modalContent(function (LawEnforcementAssessment $record) {
                $edits = $record->edits()->with(['editor', 'detailChanges', 'answerChanges'])->get()->all();

                return view(self::VIEW, [
                    'header' => self::header($record, count($edits)),
                    'edits' => self::entries($edits),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // A single edit, for the View pop-up of an edit row on the Logs page
    // (App\Filament\Resources\Logs\LogResource).
    public static function entry(): ViewEntry
    {
        return ViewEntry::make('change_history')
            ->hiddenLabel()
            ->view(self::VIEW)
            ->columnSpanFull()
            ->state(function (LogEntry $record): array {
                $edit = $record->edit->loadMissing(['assessment', 'editor', 'detailChanges', 'answerChanges']);

                return [
                    'header' => self::header($edit->assessment, null),
                    'edits' => self::entries([$edit]),
                ];
            });
    }

    /**
     * Which record the history belongs to. $editCount is null on the Logs
     * page, which shows one edit rather than the whole history.
     *
     * @return array{victim: string, offender: string, submitted: string, edits: ?int}
     */
    private static function header(?LawEnforcementAssessment $record, ?int $editCount): array
    {
        $name = fn (string $who): string => trim(
            ($record?->{$who.'FirstName'} ?? '').' '.($record?->{$who.'LastName'} ?? ''),
        ) ?: '—';

        return [
            'victim' => $name('Victim'),
            'offender' => $name('Offender'),
            // Stored UTC, shown Eastern - see App\Support\Timezones.
            'submitted' => $record?->DateCreated
                ? Carbon::parse($record->DateCreated)->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a')
                : '—',
            'edits' => $editCount,
        ];
    }

    /**
     * @param  list<AssessmentEdit>  $edits
     * @return list<array{when: string, editor: string, reason: string, changes: list<array{label: string, from: string, to: string}>}>
     */
    private static function entries(array $edits): array
    {
        return array_map(fn (AssessmentEdit $edit): array => [
            // Stored UTC, shown Eastern - see App\Support\Timezones.
            'when' => $edit->EditedAt?->copy()->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a') ?? '',
            'editor' => $edit->editor?->name ?? 'Unknown',
            'reason' => $edit->Reason,
            'changes' => $edit->changes(),
        ], $edits);
    }
}
