<?php

namespace App\Filament\Resources\Logs\Pages;

use App\Enums\LogAction;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Filament\Concerns\CapsTableSearch;
use App\Filament\Resources\Logs\LogResource;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/*
The Logs page. No header actions: rows are only ever written by
AssessmentAccessLog::record() and AssessmentEditor.

Above the table, one plain "Show" dropdown (a Filament Select in the page's own
layout, not a table filter, so it sits outside the Filters card). It is a
multi-select (2026-10-28): any mix of LogActions, shown together; none
chosen means All. It applies as soon as it changes. It only adds a where on
Action to the table's query, which is LogResource::getEloquentQuery() - the
scoped union - so it can never show a row the scope hides.
*/
class ListLogs extends ListRecords
{
    use BreadcrumbNamesThePage;
    use CapsTableSearch;

    protected static string $resource = LogResource::class;

    // The Show dropdown's value: the LogAction values chosen. Empty is All.
    /** @var list<string> */
    public array $show = [];

    // The dropdown's options, in one place: one per LogAction, labelled by
    // the enum. A new action is a new enum case. A police admin gets no
    // separate "with log" choice: they see those downloads as plain ones
    // (LogAction::shownCases()).
    public static function showOptions(): array
    {
        return collect(LogAction::shownCases(Filament::auth()->user()))
            ->mapWithKeys(fn (LogAction $action): array => [$action->value => $action->label()])
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('show')
                            ->label('Show')
                            ->multiple()
                            ->options(static::showOptions())
                            // Nothing chosen shows everything.
                            ->placeholder('All')
                            ->live(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function table(Table $table): Table
    {
        // Added to the resource's query, never in place of it.
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyShow($query));
    }

    // Back to page 1 whenever the selection changes.
    public function updatedShow(): void
    {
        $this->resetPage();
    }

    // Unknown or tampered values are dropped, and if none is left that means
    // All: it can only ever narrow.
    private function applyShow(Builder $query): Builder
    {
        $viewer = Filament::auth()->user();
        $offered = LogAction::shownCases($viewer);

        // A choice the viewer isn't offered (a police admin's "with log")
        // counts as tampered too, so it can't single those downloads out.
        $values = collect($this->show)
            ->filter(fn ($value): bool => is_string($value))
            ->map(fn (string $value): ?LogAction => LogAction::tryFrom($value))
            ->filter(fn (?LogAction $action): bool => $action !== null && in_array($action, $offered, true))
            ->flatMap(fn (LogAction $action): array => $action->matchedValues($viewer))
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('Action'), $values);
    }
}
