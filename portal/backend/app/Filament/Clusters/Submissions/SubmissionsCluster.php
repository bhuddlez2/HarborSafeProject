<?php

namespace App\Filament\Clusters\Submissions;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/*
What the public website's two forms have collected: service feedback and
resource requests.

Both are read-only in the panel. They are submissions from members of the
public, so editing one would be falsifying a record; the only actions offered
are viewing and deleting. Section 5 of Filament_CMS_Design.md gives secretary
and admin view access and nobody else.

Note the connection: these two tables live on `Feedback`, not `Content`, and
the models say so. The panel reads them through the full-access `Feedback`
connection - `FeedbackPublic` is the restricted user the public submission
endpoint uses, and it has no SELECT on submissions by design.
*/
class SubmissionsCluster extends Cluster
{
    protected static ?string $slug = 'submissions';

    protected static ?string $title = 'Submissions';

    protected static ?string $clusterBreadcrumb = 'Submissions';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string | UnitEnum | null $navigationGroup = 'Content management';

    protected static ?int $navigationSort = 2;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(
            UserRole::Admin,
            UserRole::Secretary,
        );
    }
}
