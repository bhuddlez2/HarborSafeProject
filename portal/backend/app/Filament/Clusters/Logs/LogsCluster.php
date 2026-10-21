<?php

namespace App\Filament\Clusters\Logs;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
The logs: one sidebar item, "Logs", with its two resources as tabs across the
top of the page (the Content cluster's pattern):

  1. Access log (/logs/access)  - who viewed or downloaded which form
     (AccessLogResource, AssessmentAccessLog::visibleTo())
  2. Change log (/logs/changes) - every edit to an officer assessment
     (AssessmentEditResource, AssessmentEdit::visibleTo())

Admins see every row on both; a police admin with an agency sees only their
own agency's officer-assessment rows (section 5, rule 5). Each tab gates
itself with the same rule, so Cluster::shouldRegisterNavigation() hides the
item when both refuse, and /logs opens the first tab the user can open
(Cluster::mount()). The old addresses, /access-log and /assessment-changes,
redirect here (routes/web.php).
*/
class LogsCluster extends Cluster
{
    protected static ?string $slug = 'logs';

    protected static ?string $title = 'Logs';

    protected static ?string $clusterBreadcrumb = 'Logs';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    // After Assessment review (1) and Civilian assessments (2). A police
    // admin finds it in their single "Police Admin" group instead.
    protected static string | UnitEnum | null $navigationGroup = 'Assessments';

    protected static ?int $navigationSort = 3;

    // Tabs, not a second sidebar.
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    // Grouping and order only - see App\Filament\PoliceAdminNavigation.
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return PoliceAdminNavigation::applies() ? PoliceAdminNavigation::LOGS : parent::getNavigationSort();
    }

    // Belt and braces for a direct hit on /logs: the cluster page is not a
    // resource, so nothing else gates it. The same rule as both tabs.
    public static function canAccess(): bool
    {
        return self::canOpenLogs();
    }

    // Active admins, and active police admins with an agency. Shared by both
    // tabs, so the three can't disagree.
    public static function canOpenLogs(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->hasActiveRole(UserRole::Admin)
            || $user?->managedAgencyId() !== null;
    }
}
