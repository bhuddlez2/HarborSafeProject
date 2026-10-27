<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Filament\Concerns\CapsTableSearch;
use App\Enums\FollowUpStatus;
use App\Filament\Resources\FollowUps\FollowUpResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

// The queue, a tab per status with its count - easier than a filter to see
// how many forms sit at each stage. Counts follow the user's own scope
// (FollowUpResource::getEloquentQuery()), so a secretary's count resource
// requests only.
class ListFollowUps extends ListRecords
{
    use CapsTableSearch;

    protected static string $resource = FollowUpResource::class;

    // The sidebar's words. Filament's default, from the model label, would
    // read "Follow-ups".
    public function getTitle(): string
    {
        return FollowUpResource::getNavigationLabel();
    }

    public function getTabs(): array
    {
        return collect(FollowUpStatus::cases())
            ->mapWithKeys(fn (FollowUpStatus $status): array => [
                $status->value => Tab::make($status->label())
                    ->badge(fn (): int => FollowUpResource::getEloquentQuery()->where('Status', $status->value)->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('Status', $status->value)),
            ])
            ->all();
    }

    public function getDefaultActiveTab(): string
    {
        return FollowUpStatus::NeedsFollowUp->value;
    }
}
