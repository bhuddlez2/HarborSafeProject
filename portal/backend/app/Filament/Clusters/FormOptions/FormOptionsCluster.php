<?php

namespace App\Filament\Clusters\FormOptions;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
The dropdown choices behind the public website's forms: services on the
feedback form, resource types and counties on the resource-request form.

This is the "inputs for the feedback form and resource request form can be
updated" requirement - staff change what the public can pick without a deploy.

Editing here is immediately visible on the live site, because the public
endpoints (/api/public/services, /resources, /counties) read these same three
tables. Renaming a row does not break existing submissions - those hold the
id, so an old feedback row keeps pointing at the renamed service - but
DELETING one that submissions reference does. Each resource below guards its
own delete accordingly.
*/
class FormOptionsCluster extends Cluster
{
    protected static ?string $slug = 'form-options';

    protected static ?string $title = 'Form options';

    protected static ?string $clusterBreadcrumb = 'Form options';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string | UnitEnum | null $navigationGroup = 'Content management';

    protected static ?int $navigationSort = 3;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }
}
