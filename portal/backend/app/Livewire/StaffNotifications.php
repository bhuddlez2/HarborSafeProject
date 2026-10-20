<?php

namespace App\Livewire;

use App\Models\StaffNotification;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/*
The staff panel's bell (StaffPanelProvider ->databaseNotifications()): Filament's
panel notification component (the one that draws the sidebar bell and takes
its polling setting, off, from the panel - not the plain notifications one,
which does neither), listing only what the bell should still show - every
unread alert, and read ones for 12 hours after they were read
(StaffNotification::KEPT_AFTER_READ_HOURS). Older ones are gone from the list
and pruned from the table.

Opening the panel marks everything read after about five seconds (the
staff-notifications partial, a render hook); "Mark all as read" in the
panel's header does the same at once.
*/
class StaffNotifications extends DatabaseNotifications
{
    public function getNotificationsQuery(): Builder | Relation
    {
        /** @var Relation<StaffNotification> $query */
        $query = parent::getNotificationsQuery();

        return $query->shownInBell();
    }
}
