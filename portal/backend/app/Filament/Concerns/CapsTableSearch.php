<?php

namespace App\Filament\Concerns;

use App\Filament\Tables\TableSearch;

/*
Cuts a table's search, and each search-by-field box, to
TableSearch::MAX_LENGTH characters on the server.
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

    // The same cap on each search-by-field box (the assessment tables' Victim,
    // Offender and Submitted boxes), which Filament reads through here.
    public function getTableColumnSearches(): array
    {
        return array_map(
            fn ($search) => is_string($search) ? mb_substr($search, 0, TableSearch::MAX_LENGTH) : $search,
            parent::getTableColumnSearches(),
        );
    }
}
