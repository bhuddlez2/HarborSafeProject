<?php

namespace App\Filament\Clusters\Logs\Resources\AccessLog\Pages;

use App\Enums\AssessmentAccess;
use App\Filament\Clusters\Logs\Resources\AccessLog\AccessLogResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
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
The Access log tab. No header actions: rows are only ever written by
AssessmentAccessLog::record().

Above the table, one plain "Show" dropdown (a Filament Select in the page's own
layout, not a table filter, so there is no Filters button, menu or card):
All, or one action. It applies as soon as it changes. It only adds a where on
Action to the table's query, which is AccessLogResource::getEloquentQuery() -
the visibleTo() scope - so it can never show a row the scope hides.
*/
class ListAccessLog extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = AccessLogResource::class;

    public const SHOW_ALL = 'all';

    // The Show dropdown's value: SHOW_ALL or an AssessmentAccess value.
    public ?string $show = self::SHOW_ALL;

    // The dropdown's options, in one place: All, then one per
    // AssessmentAccess case, labelled by the enum. A new action (say
    // "Exported") is a new enum case with its label, and appears here.
    public static function showOptions(): array
    {
        return [
            self::SHOW_ALL => 'All',
            ...collect(AssessmentAccess::cases())
                ->mapWithKeys(fn (AssessmentAccess $action): array => [$action->value => $action->label()])
                ->all(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('show')
                            ->label('Show')
                            ->options(static::showOptions())
                            ->selectablePlaceholder(false)
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

    // An unknown or tampered value means All: it can only ever narrow.
    private function applyShow(Builder $query): Builder
    {
        $action = AssessmentAccess::tryFrom((string) $this->show);

        return $action === null ? $query : $query->where('Action', $action->value);
    }
}
