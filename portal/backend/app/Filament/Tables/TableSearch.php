<?php

namespace App\Filament\Tables;

/*
The longest search anyone can type into a table's search box. Searches here
are names, titles and emails, so 100 is plenty.

Enforced twice:
  - in the browser, by resources/views/vendor/filament-tables/components/search-field.blade.php
    (a copy of Filament's view with its maxlength of 1000 changed to this);
  - on the server, by App\Filament\Concerns\CapsTableSearch, which every page
    with a table uses, since the browser's limit is easy to get round.
*/
final class TableSearch
{
    public const MAX_LENGTH = 100;
}
