<?php

namespace App\Filament\Assessments;

use App\Models\AssessmentAccessLog;
use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Models\LogEntry;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
scoped through LawEnforcementAssessment::visibleTo(). Who may see the VIEWS of
a record is AssessmentAccessLog::visibleTo() (admins all, police admins their
agency's, officers none), applied below - never a rule of its own.
*/
final class ChangeHistory
{
    public const VIEW = 'filament.infolists.change-history';

    /*
    The row button, before ViewAction. Needs ->withCount('edits') on the table
    query.

    Without $withAccess (My Assessments): "Change log" - every edit since
    submission, newest first. Officers may not see who opened a record
    (AssessmentAccessLog::visibleTo()), so their log stays edits only. Purple
    on an edited record; light gray and disabled on one never edited (the
    gray is theme.css's .fi-disabled rule).

    With $withAccess (Assessment review, team plan task 22): "Log" - every
    view, PDF download and edit since submission, interleaved newest first,
    with the submission itself at the bottom. Always openable, since every
    record has a history once anyone has opened it; still purple when edited.
    The Change log page stays edits only (entry() below).
    */
    public static function action(bool $withAccess = false): Action
    {
        return Action::make('changeLog')
            ->label($withAccess ? 'Log' : 'Change log')
            ->icon(Heroicon::OutlinedClock)
            ->color(fn (LawEnforcementAssessment $record): string => $record->edits_count ? 'primary' : 'gray')
            ->disabled(fn (LawEnforcementAssessment $record): bool => ! $withAccess && ! $record->edits_count)
            ->tooltip(fn (LawEnforcementAssessment $record): string => match (true) {
                $withAccess => 'Every view, download and edit since submission',
                (bool) $record->edits_count => 'Every edit since submission',
                default => 'No edits since submission',
            })
            ->modalHeading($withAccess ? 'Log' : 'Change log')
            // Old and new values are the record's contents, so opening this
            // is a view. mountUsing, not afterFormFilled: this pop-up has no
            // schema, and Filament only calls afterFormFilled when there is
            // one. Not in modalContent(), which re-runs on every render.
            // Logged before the content is built, so the Log includes this
            // opening too.
            ->mountUsing(fn (LawEnforcementAssessment $record) => AssessmentViews::log($record))
            ->modalContent(function (LawEnforcementAssessment $record) use ($withAccess) {
                $edits = $record->edits()->with(['editor', 'detailChanges', 'answerChanges'])->get()->all();

                if (! $withAccess) {
                    return view(self::VIEW, [
                        'header' => self::header($record, count($edits)),
                        'edits' => self::entries($edits),
                    ]);
                }

                $access = AssessmentAccessLog::visibleTo(Filament::auth()->user())
                    ->where('LawEnforcementDocumentID', $record->getKey())
                    ->with('user')
                    ->get();

                return view(self::VIEW, [
                    'header' => [
                        ...self::header($record, count($edits)),
                        'title' => 'Log',
                        'views' => $access->count(),
                    ],
                    'events' => self::timeline($record, $edits, $access->all()),
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
     * Views, downloads and edits of one record, newest first, then the
     * submission. Each item: kind ('edit' | 'view' | 'download' |
     * 'submitted'), plus the edit's fields (entries()) or who and when.
     *
     * @param  list<AssessmentEdit>  $edits
     * @param  list<AssessmentAccessLog>  $access
     * @return list<array<string, mixed>>
     */
    private static function timeline(LawEnforcementAssessment $record, array $edits, array $access): array
    {
        $when = fn ($at): string => $at
            ? Carbon::parse($at)->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a')
            : '';

        $events = [];

        foreach ($edits as $edit) {
            $events[] = ['kind' => 'edit', 'at' => $edit->EditedAt, ...self::entries([$edit])[0]];
        }

        foreach ($access as $row) {
            // A download with the follow-up log reads as a plain download to
            // a police admin (AssessmentAccess::shownTo()).
            $shown = $row->Action->shownTo(Filament::auth()->user());

            $events[] = [
                'kind' => $shown->value,
                'at' => $row->AccessedAt,
                'when' => $when($row->AccessedAt),
                'who' => $row->user?->name ?? 'Unknown',
                'label' => $shown->label(),
            ];
        }

        // Newest first. Stable for equal times, so an edit keeps its place
        // beside the view logged when its pop-up opened.
        usort($events, fn (array $a, array $b): int => Carbon::parse($b['at'])->getTimestamp() <=> Carbon::parse($a['at'])->getTimestamp());

        $record->loadMissing('submitter');

        $events[] = [
            'kind' => 'submitted',
            'when' => $when($record->DateCreated),
            'who' => $record->submitter?->name ?? 'Unknown',
            'label' => 'Submitted',
        ];

        return array_map(function (array $event): array {
            unset($event['at']);

            return $event;
        }, $events);
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
