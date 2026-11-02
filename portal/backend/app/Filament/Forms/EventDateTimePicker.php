<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\DateTimePicker;

/*
An event's Starts and Ends (Burchfield B46): date and time, or date only for an
all-day event. Filament converts time zones only while the time shows, so a
date-only picker would save UTC midnight (the evening before in Eastern) and
read a saved event back by its UTC day. Converting in both modes saves an
all-day event at midnight Eastern and reads every event by its Eastern day.
*/
class EventDateTimePicker extends DateTimePicker
{
    protected function getStateCastTimezone(): ?string
    {
        return $this->getTimezone();
    }
}
