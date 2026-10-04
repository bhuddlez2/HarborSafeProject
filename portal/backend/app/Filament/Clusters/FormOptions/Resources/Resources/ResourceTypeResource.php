<?php

namespace App\Filament\Clusters\FormOptions\Resources\Resources;

use App\Filament\Clusters\FormOptions\Resources\LookupResource;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Models\Resource as ResourceType;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/*
The "what are you interested in?" checkboxes on the resource-request form.

Named ResourceTypeResource rather than ResourceResource because
App\Models\Resource collides with Filament's own Resource base class on sight;
the import is aliased for the same reason.
*/
class ResourceTypeResource extends LookupResource
{
    protected static ?string $model = ResourceType::class;

    protected static ?string $slug = 'resource-types';

    protected static ?string $title = 'Resource types';

    protected static ?string $navigationLabel = 'Resource types';

    protected static ?string $modelLabel = 'resource type';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    protected static function formDescription(): string
    {
        return 'These are the choices on the resource request form.';
    }

    protected static function isInUse(Model $record): bool
    {
        // Referenced through the resource_request_resource_types pivot, which
        // replaced the old singular FK in migration 2026_09_15_090000.
        return $record->requests()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResourceTypes::route('/'),
        ];
    }
}
