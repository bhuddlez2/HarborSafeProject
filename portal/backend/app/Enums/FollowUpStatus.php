<?php

namespace App\Enums;

/*
Where Harbor Safe's follow-up on a form stands (team plan task 16; roadmap
Phase Z). Harbor Safe decides when to use each.

Needs follow-up is where every form starts, and nothing ever returns to it.
Reached, Could not reach and Declined are final: an assessment may still move
between them, a resource request may not (a new request is started instead).
*/
enum FollowUpStatus: string
{
    case NeedsFollowUp = 'needs_follow_up';
    case AttemptedContact = 'attempted_contact';
    case Reached = 'reached';
    case CouldNotReach = 'could_not_reach';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NeedsFollowUp => 'Needs follow-up',
            self::AttemptedContact => 'Attempted contact',
            self::Reached => 'Reached',
            self::CouldNotReach => 'Could not reach',
            self::Declined => 'Declined',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Reached, self::CouldNotReach, self::Declined], true);
    }

    // Whether a form of this kind may move from this status to $to. Staying in
    // the same status isn't a move: that is a note on its own.
    public function canMoveTo(self $to, FollowUpKind $kind): bool
    {
        if ($to === $this || $to === self::NeedsFollowUp) {
            return false;
        }

        if ($this->isFinal()) {
            return $kind->finalsAreMovable() && $to->isFinal();
        }

        return $this === self::NeedsFollowUp || $to->isFinal();
    }

    /** @return list<self> The statuses a form of $kind may move to from here. */
    public function nextFor(FollowUpKind $kind): array
    {
        return array_values(array_filter(self::cases(), fn (self $to): bool => $this->canMoveTo($to, $kind)));
    }
}
