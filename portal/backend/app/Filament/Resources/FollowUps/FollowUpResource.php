<?php

namespace App\Filament\Resources\FollowUps;

use App\Enums\FollowUpKind;
use App\Filament\Concerns\AllowsOnlyListedActions;
use App\Filament\FollowUps\FollowUpLog;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\FollowUps\Pages\ViewFollowUp;
use App\Filament\Tables\AssessmentFilters;
use App\Filament\Tables\RecordCards;
use App\Models\FollowUp;
use App\Support\Timezones;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
The Follow-ups queue (team plan task 16; roadmap Phase Z): every form Harbor
Safe follows up, in one place, a tab per status. Each row opens the form's
follow-up page (ViewFollowUp), and its Log button shows the history.

Who sees what is FollowUp::visibleTo(): admins every officer assessment,
civilian assessment and resource request; secretaries resource requests only;
officers and police admins nothing at all (canAccess() refuses them, and the
query would be empty anyway). Never add a column or filter that reaches past
that scope.

No name search, by design: resource requests live in another database, and
searching across both would mean copying names into follow_ups. Sorting and
filtering are by type, date and status.
*/
class FollowUpResource extends Resource
{
    use AllowsOnlyListedActions;

    protected static ?string $model = FollowUp::class;

    protected static ?string $slug = 'follow-ups';

    protected static ?string $navigationLabel = 'Follow-ups';

    protected static ?string $modelLabel = 'follow-up';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhone;

    // The day's work queue: first under Content management, and the page
    // admins, secretaries and super admins land on (App\Filament\StaffLanding).
    protected static string | \UnitEnum | null $navigationGroup = 'Content management';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return FollowUp::kindsFor(Filament::auth()->user()) !== [];
    }

    // Open a follow-up page, nothing else. Updates go through the page's own
    // Update follow-up action and App\Services\FollowUps, never an Edit button;
    // follow-ups open and close with their forms, never by hand.
    protected static function allowedActions(): array
    {
        return ['view'];
    }

    // Only a follow-up of a kind this user works (FollowUpKind::workedBy()).
    protected static function allowsRecord(string $action, Model $record): bool
    {
        return $record instanceof FollowUp && $record->isWorkableBy(Filament::auth()->user());
    }

    // Scopes the queue and the record lookup behind the follow-up page, so a
    // follow-up outside the user's kinds is a 404.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Filament::auth()->user())
            ->with(['lawEnforcementAssessment', 'privateAssessment', 'resourceRequest', 'lastUpdatedBy']);
    }

    public static function table(Table $table): Table
    {
        $seesSeveralKinds = fn (): bool => count(FollowUp::kindsFor(Filament::auth()->user())) > 1;

        // A card on a phone, and stacked buttons on narrower screens
        // (RecordCards): name and type on top, then the two dates.
        return RecordCards::apply($table)
            ->columns([
                // The requester for a resource request, the victim for an
                // assessment. Read from the form itself, never copied.
                RecordCards::title(TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (FollowUp $record): string => $record->formName() ?? '-')),

                RecordCards::corner(TextColumn::make('Kind')
                    ->label('Type')
                    ->formatStateUsing(fn (FollowUpKind $state): string => $state->label())
                    ->visible($seesSeveralKinds)),

                RecordCards::meta(TextColumn::make('SubmittedAt')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Timezones::DISPLAY)
                    ->sortable()),

                RecordCards::meta(TextColumn::make('LastUpdatedAt')
                    ->label('Last updated')
                    ->dateTime('M j, Y g:i a')
                    ->timezone(Timezones::DISPLAY)
                    ->description(fn (FollowUp $record): ?string => $record->lastUpdatedBy?->name)
                    ->placeholder('Not yet')
                    ->sortable()),
            ])
            ->defaultSort('SubmittedAt', 'asc')
            ->filters([
                SelectFilter::make('Kind')
                    ->label('Type')
                    ->options(fn (): array => collect(FollowUp::kindsFor(Filament::auth()->user()))
                        ->mapWithKeys(fn (FollowUpKind $kind): array => [$kind->value => $kind->label()])
                        ->all())
                    ->visible($seesSeveralKinds),

                AssessmentFilters::dateRange('date_submitted', 'SubmittedAt', 'Submitted'),
            ])
            ->recordUrl(fn (FollowUp $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                FollowUpLog::action(),
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(fn (FollowUp $record): string => static::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Nothing here')
            ->emptyStateDescription('Forms in this stage of follow-up appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowUps::route('/'),
            'view' => ViewFollowUp::route('/{record}'),
        ];
    }
}
