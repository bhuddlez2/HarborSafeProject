<?php

namespace App\Enums;

/*
The kinds of form Harbor Safe follows up (team plan task 16): both kinds of
assessment and resource requests. Never service feedback, which is data
collection only.

Who works a kind's follow-ups is who is alerted when one arrives
(NewSubmission::alertedRoles()): admins and super admins for assessments, secretaries and
admins for resource requests - so adding the super admin there covers both.
*/
enum FollowUpKind: string
{
    case LawEnforcementAssessment = 'law_enforcement_assessment';
    case CivilianAssessment = 'civilian_assessment';
    case ResourceRequest = 'resource_request';

    public function label(): string
    {
        return match ($this) {
            self::LawEnforcementAssessment => 'Officer assessment',
            self::CivilianAssessment => 'Civilian assessment',
            self::ResourceRequest => 'Resource request',
        };
    }

    // The follow_ups column that points at this kind of form.
    public function pointerColumn(): string
    {
        return match ($this) {
            self::LawEnforcementAssessment => 'LawEnforcementDocumentID',
            self::CivilianAssessment => 'PrivateDocumentID',
            self::ResourceRequest => 'ResourceRequestFormID',
        };
    }

    public function submission(): NewSubmission
    {
        return match ($this) {
            self::LawEnforcementAssessment => NewSubmission::LawEnforcementAssessment,
            self::CivilianAssessment => NewSubmission::CivilianAssessment,
            self::ResourceRequest => NewSubmission::ResourceRequest,
        };
    }

    /** @return list<UserRole> */
    public function workedBy(): array
    {
        return $this->submission()->alertedRoles();
    }

    public function isAssessment(): bool
    {
        return $this !== self::ResourceRequest;
    }
}
