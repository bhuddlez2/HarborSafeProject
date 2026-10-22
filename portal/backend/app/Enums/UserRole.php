<?php

namespace App\Enums;

/*
Every role is granted explicitly, screen by screen, through
User::hasActiveRole(): no role inherits another's access.

SuperAdmin is Harbor Safe's top account (team plan task 15): everything admin
has, plus Agencies and every account role on Accounts, other super admins
included, so ownership can pass on when one leaves. It is still view-only on
assessments - it never submits, edits or deletes one - and has no Officer
Portal. The first one comes from `php artisan staff:create-super-admin`.

It is NOT Shield's spatie "super_admin" role, which shares the name: that one
skips every Gate check, including the `false` on assessment edits in
LawEnforcementAssessmentPolicy. Never assign it to anyone.
*/
enum UserRole: string
{
    case LawEnforcement = 'law_enforcement';
    case Secretary = 'secretary';
    case Admin = 'admin';
    case PoliceAdmin = 'police_admin';
    case SuperAdmin = 'super_admin';

    // How the panel names the role: on Accounts' role picker and badge.
    public function label(): string
    {
        return match ($this) {
            self::LawEnforcement => 'Officer',
            self::Secretary => 'Secretary',
            self::Admin => 'Admin',
            self::PoliceAdmin => 'Police Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    // The roles that belong to an agency, through a law_enforcement_agents row.
    public function needsAgency(): bool
    {
        return in_array($this, [self::LawEnforcement, self::PoliceAdmin], true);
    }
}
