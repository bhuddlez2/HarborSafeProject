<?php

namespace App\Filament\Concerns;

use App\Filament\Tables\TableSearch;

/*
Cuts a table's search to TableSearch::MAX_LENGTH characters on the server.
Every search Filament runs - typed, restored from the session or from the
address bar - reads it through getTableSearch(), so this is the one place to
cap it. Every page with a table uses this trait; InputCapsTest fails on
one that doesn't.
*/
trait CapsTableSearch
{
    public function getTableSearch(): ?string
    {
        $search = parent::getTableSearch();

        return $search === null ? null : mb_substr($search, 0, TableSearch::MAX_LENGTH);
    }
}
