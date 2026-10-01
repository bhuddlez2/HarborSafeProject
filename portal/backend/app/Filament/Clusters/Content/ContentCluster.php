<?php

namespace App\Filament\Clusters\Content;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
Website content: events, newsletters and the category lookup behind them.

A cluster rather than three loose sidebar entries for two reasons. It gives the
whole group one address space - /staff/content/events, /staff/content/newsletters
- which is what separates content management from the officer portal's
/staff/police/*. And it renders its members as tabs across the top of each
page (SubNavigationPosition::Top), so moving between events and newsletters
does not mean a round trip through the sidebar.

Visibility needs no canAccess() here: Cluster::shouldRegisterNavigation()
already hides the cluster when every resource inside it refuses the user, and
each resource carries the matrix rule itself. Opening /staff/content directly
redirects to the first tab the user can actually see (Cluster::mount()).
*/
class ContentCluster extends Cluster
{
    protected static ?string $slug = 'content';

    protected static ?string $title = 'Content';

    protected static ?string $clusterBreadcrumb = 'Content';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string | UnitEnum | null $navigationGroup = 'Content management';

    protected static ?int $navigationSort = 1;

    // Tabs, not a second sidebar.
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    // Belt and braces for a direct URL hit: the cluster page itself is not a
    // resource, so nothing else gates it.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }
}
