<?php

namespace App\Filament;

use App\Enums\UserRole;
use Filament\Facades\Filament;

/*
The police admin's sidebar: one "Police Admin" group holding, in order, Home,
New assessment, Assessment review, Logs, Officers and Account.

Home, New assessment, Assessment review, Logs and Account are shared with
other roles, which keep their own groups (Officer Portal, Assessments). Those
items ask applies() and take the group and position below only for a police
admin, so this file is the one place the police admin's order is declared.
Grouping and order only - who may open each item is still its own
canAccess().

The sorts leave gaps of 10 so later items can slot in between.
*/
final class PoliceAdminNavigation
{
    public const GROUP = 'Police Admin';

    public const HOME = 10;

    // The same LAP wizard officers use, for the police admin's own
    // assessments.
    public const NEW_ASSESSMENT = 20;

    public const ASSESSMENT_REVIEW = 30;

    // Logs: views, downloads and edits in one list (LogResource).
    public const LOGS = 40;

    public const OFFICERS = 50;

    public const ACCOUNT = 60;

    // By users.role, like every other role check in the panel.
    public static function applies(): bool
    {
        return (bool) Filament::auth()->user()?->hasActiveRole(UserRole::PoliceAdmin);
    }
}
