<?php

namespace App\Enums;

use App\Filament\Clusters\Submissions\Resources\ResourceRequests\ResourceRequestResource;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\CivilianAssessments\CivilianAssessmentResource;
use App\Models\User;
use Filament\Support\Icons\Heroicon;

/*
The kinds of submission that raise an alert in the staff panel's bell (team
plan task 17), and who hears about each - decided here and nowhere else:

- Resource requests and service feedback from the website: secretaries,
  admins and super admins. Feedback that staff typed in themselves (ServiceFeedback::
  recordStaffEntry()) never alerts: whoever entered it already knows.
- Lethality assessments, from the website or from an officer: admins and
  super admins only.
  Secretaries never see assessment PII; officers and police admins are never
  alerted, since they already know about the call.

Alerts carry no names or details - just what arrived and a link to the
screen where the recipient can open it.

A new Harbor Safe role goes in every alertedRoles() list below.
*/
enum NewSubmission
{
    case ResourceRequest;
    case ServiceFeedback;
    case CivilianAssessment;
    case LawEnforcementAssessment;

    // Whether this user gets the bell at all: active, and in a role some kind
    // of submission alerts. Officers and police admins never do, so they see
    // no bell rather than one that is always empty.
    public static function reaches(?User $user): bool
    {
        if (! $user?->is_active) {
            return false;
        }

        foreach (self::cases() as $submission) {
            if ($user->hasActiveRole(...$submission->alertedRoles())) {
                return true;
            }
        }

        return false;
    }

    /** @return list<UserRole> */
    public function alertedRoles(): array
    {
        return match ($this) {
            self::ResourceRequest, self::ServiceFeedback => [UserRole::Secretary, UserRole::Admin, UserRole::SuperAdmin],
            self::CivilianAssessment, self::LawEnforcementAssessment => [UserRole::Admin, UserRole::SuperAdmin],
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::ResourceRequest => 'New resource request',
            self::ServiceFeedback => 'New service feedback',
            self::CivilianAssessment => 'New civilian assessment',
            self::LawEnforcementAssessment => 'New officer assessment',
        };
    }

    public function body(): string
    {
        return match ($this) {
            self::LawEnforcementAssessment => 'Submitted by an officer.',
            default => 'Submitted on the website.',
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::ResourceRequest => Heroicon::OutlinedLifebuoy,
            self::ServiceFeedback => Heroicon::OutlinedChatBubbleLeftEllipsis,
            self::CivilianAssessment, self::LawEnforcementAssessment => Heroicon::OutlinedClipboardDocumentList,
        };
    }

    // Where the alert's View button goes: the list the record appears in.
    // Relative, so it works whichever address the alert was raised from.
    public function url(): string
    {
        $resource = match ($this) {
            self::ResourceRequest => ResourceRequestResource::class,
            self::ServiceFeedback => ServiceFeedbackResource::class,
            self::CivilianAssessment => CivilianAssessmentResource::class,
            self::LawEnforcementAssessment => AssessmentReviewResource::class,
        };

        return $resource::getUrl('index', isAbsolute: false, panel: 'staff');
    }
}
