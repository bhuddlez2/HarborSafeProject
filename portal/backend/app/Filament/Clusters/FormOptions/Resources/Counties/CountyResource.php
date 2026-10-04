<?php

namespace App\Filament\Clusters\FormOptions\Resources\Counties;

use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\LookupResource;
use App\Models\County;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

// The county dropdown on the resource-request form.
class CountyResource extends LookupResource
{
    protected static ?string $model = County::class;

    protected static ?string $slug = 'counties';

    protected static ?string $title = 'Counties';

    protected static ?string $navigationLabel = 'Counties';

    protected static ?string $modelLabel = 'county';

    protected static ?string $pluralModelLabel = 'counties';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 3;

    protected static function formDescription(): string
    {
        return 'These are the choices in the county dropdown on the resource request form.';
    }

    protected static function isInUse(Model $record): bool
    {
        return $record->requests()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCounties::route('/'),
        ];
    }
}
