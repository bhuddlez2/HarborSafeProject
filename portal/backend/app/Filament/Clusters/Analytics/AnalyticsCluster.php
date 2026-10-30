<?php

namespace App\Filament\Clusters\Analytics;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
Analytics (team plan task 28): totals and charts for Harbor Safe staff, one
sidebar item in its own "Reports" group, its four tabs across the top:

  1. Law-enforcement assessments (/analytics/officer-assessments)  admin, super admin
  2. Civilian assessments        (/analytics/civilian-assessments) admin, super admin
  3. Feedback and resource requests (/analytics/feedback-and-requests)
                                 admin, super admin, secretary
  4. Follow-ups                  (/analytics/follow-ups)           admin, super admin

Officers and police admins never reach it: it counts every agency together.
Police admins have their own agency's page instead (App\Filament\Pages\AgencyAnalytics).
Each tab gates itself; /analytics opens the first tab the user may open
(Cluster::mount()), so a secretary lands on tab 3. The shared date range and
filter live in App\Filament\Analytics\AnalyticsPage.
*/
class AnalyticsCluster extends Cluster
{
    protected static ?string $slug = 'analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $clusterBreadcrumb = 'Analytics';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    // A direct hit on /analytics: the widest audience of any tab.
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::SuperAdmin,
            UserRole::Secretary,
        );
    }
}
