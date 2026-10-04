<?php

namespace App\Filament\Clusters\FormOptions\Resources\Services;

use App\Filament\Clusters\FormOptions\Resources\LookupResource;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

// The "which service?" dropdown on the website's service feedback form.
class ServiceResource extends LookupResource
{
    protected static ?string $model = Service::class;

    protected static ?string $slug = 'services';

    protected static ?string $title = 'Services';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'service';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 1;

    protected static function formDescription(): string
    {
        return 'These are the choices on the service feedback form.';
    }

    protected static function isInUse(Model $record): bool
    {
        // service_feedback.ServiceID is a non-nullable FK, so any referencing
        // row blocks the delete.
        return $record->feedback()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServices::route('/'),
        ];
    }
}
