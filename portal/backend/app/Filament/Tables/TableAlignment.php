<?php

namespace App\Filament\Tables;

use Filament\Tables\Columns\Column;

/*
How a table column lines up with its heading (roadmap Phase K):

  - TEXT columns - dates, names, titles, emails, free text - are left-aligned
    with their heading. That is Filament's default, so a text column simply
    has no alignment call. Don't add ->alignCenter() to one.
  - SYMBOL columns - yes/no icons and badges (a category, a rating, a
    count, Online/Staff) - are centred under the heading's words. Wrap them in
    symbol().

"Under the heading's words" is the part Filament can't do on its own:
->alignCenter() centres the label and its sort arrow together, which pushes
the label left of the column's centre on any sortable column. symbol() also
tags the header (hs-symbol-header) so theme.css takes the arrow out of that
calculation; the label sits dead centre over the icons, with the arrow just
after it.

Applied to the content-management tables, Civilian assessments and Accounts.
The police-side tables (Assessment review, My Assessments, Officers, Change
log) are being aligned separately and can adopt this when they are.
*/
final class TableAlignment
{
    public const SYMBOL_HEADER_CLASS = 'hs-symbol-header';

    /**
     * @template T of Column
     *
     * @param  T  $column
     * @return T
     */
    public static function symbol(Column $column): Column
    {
        return $column
            ->alignCenter()
            ->extraHeaderAttributes(['class' => self::SYMBOL_HEADER_CLASS]);
    }
}
