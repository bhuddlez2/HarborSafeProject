<?php

namespace App\Enums;

enum UserRole: string
{
    case LawEnforcement = 'law_enforcement';
    case Secretary = 'secretary';
    case Admin = 'admin';
    case PoliceAdmin = 'police_admin';
}
