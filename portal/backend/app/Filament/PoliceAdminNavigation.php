<?php

namespace App\Filament;

use App\Enums\UserRole;
use Filament\Facades\Filament;

/*
The police admin's sidebar: one "Police Admin" group holding, in order, Home,
Assessment review, Change log, Officers and Account.

Home, Assessment review, Change log and Account are shared with other roles,
which keep their own groups (Officer Portal, Assessments). Those items ask
applies() and take the group and position below only for a police admin, so
this file is the one place the police admin's order is declared. Grouping and
order only - who may open each item is still its own canAccess().

The sorts leave gaps of 10 so later items can slot in between.
*/
final class PoliceAdminNavigation
{
    public const GROUP = 'Police Admin';

    public const HOME = 10;

    public const ASSESSMENT_REVIEW = 20;

    public const CHANGE_LOG = 30;

    public const OFFICERS = 40;

    public const ACCOUNT = 50;

    // By users.role, like every other role check in the panel.
    public static function applies(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::PoliceAdmin);
    }
}
