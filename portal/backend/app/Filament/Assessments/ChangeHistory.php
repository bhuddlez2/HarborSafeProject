<?php

namespace App\Filament\Assessments;

use App\Models\AssessmentEdit;
use App\Models\LawEnforcementAssessment;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Infolists\Components\ViewEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

/*
The change history of a law-enforcement assessment, shared by every screen
that shows one: a "Change log" button beside View on My Assessments and
Assessment review, and the Change log page (one edit at a time). One Blade
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
            ->modalDescription(fn (LawEnforcementAssessment $record): string => 'Assessment submitted '
                .($record->DateCreated ? Carbon::parse($record->DateCreated)->setTimezone(Timezones::DISPLAY)->format('M j, Y g:i a') : '')
                .'. Every edit since, newest first. Edits cannot be changed or removed.')
            ->modalContent(fn (LawEnforcementAssessment $record) => view(self::VIEW, [
                'edits' => self::entries(
                    $record->edits()->with(['editor', 'detailChanges', 'answerChanges'])->get()->all(),
                ),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // A single edit, for the Change log page.
    public static function entry(): ViewEntry
    {
        return ViewEntry::make('change_history')
            ->hiddenLabel()
            ->view(self::VIEW)
            ->state(fn (AssessmentEdit $record): array => self::entries([
                $record->loadMissing(['editor', 'detailChanges', 'answerChanges']),
            ]));
    }

    // "Amended" on any record that has been edited since submission. Needs
    // ->withCount('edits') on the table query.
    public static function amendedColumn(): TextColumn
    {
        return TextColumn::make('edits_count')
            ->label('Status')
            ->badge()
            ->formatStateUsing(fn (?int $state): string => $state ? 'Amended' : 'Submitted')
            ->color(fn (?int $state): string => $state ? 'warning' : 'gray')
            ->tooltip(fn (?int $state): ?string => $state ? "Edited {$state} ".str('time')->plural($state).' since submission' : null);
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
