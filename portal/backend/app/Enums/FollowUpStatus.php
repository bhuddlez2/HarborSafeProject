<?php

namespace App\Enums;

/*
Where Harbor Safe's follow-up on a form stands (team plan task 16; roadmap
Phase Z; Finishing Touches item 25). Harbor Safe decides when to use each.

Needs Follow-Up is where every form starts, and nothing ever returns to it.
Could Not Reach, Declined and Closed are final, for every kind of form: a
final form can't change status at all (a note on its own still can be added).
Reached isn't final, but can move on only to Closed. Closed retires a
follow-up, and any form that isn't final may move to it.
*/
enum FollowUpStatus: string
{
    case NeedsFollowUp = 'needs_follow_up';
    case AttemptedContact = 'attempted_contact';
    case Reached = 'reached';
    case CouldNotReach = 'could_not_reach';
    case Declined = 'declined';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NeedsFollowUp => 'Needs Follow-Up',
            self::AttemptedContact => 'Attempted Contact',
            self::Reached => 'Reached',
            self::CouldNotReach => 'Could Not Reach',
            self::Declined => 'Declined',
            self::Closed => 'Closed',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CouldNotReach, self::Declined, self::Closed], true);
    }

    // Whether a form may move from this status to $to, the same for every kind
    // of form. Staying in the same status isn't a move: that is a note on its own.
    public function canMoveTo(self $to): bool
    {
        if ($to === $this || $to === self::NeedsFollowUp || $this->isFinal()) {
            return false;
        }

        // Needs Follow-Up and Attempted Contact may go anywhere else; Reached only to Closed.
        return $this !== self::Reached || $to === self::Closed;
    }

    /** @return list<self> The statuses a form may move to from here. */
    public function next(): array
    {
        return array_values(array_filter(self::cases(), fn (self $to): bool => $this->canMoveTo($to)));
    }
}
