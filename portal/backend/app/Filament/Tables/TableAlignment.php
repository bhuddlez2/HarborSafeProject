<?php

namespace App\Filament\Tables;

use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;

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

  - ROW BUTTONS, on the assessment tables (through AssessmentColumns::cards()),
    are centred in their column like symbols, side by side and all the same
    height. buttons() centres them and tags the table (hs-row-buttons) so
    theme.css can put each button's icon and label on one line: the panel's
    pill-button rule makes them inline-block, which let the Log and View
    buttons come out different heights.

Applied to every table in the panel, including the police-side ones
(Assessment review and My Assessments through AssessmentColumns, Officers,
Logs). TableAlignmentTest checks each one column by column.
*/
final class TableAlignment
{
    public const SYMBOL_HEADER_CLASS = 'hs-symbol-header';

    public const BUTTONS_CLASS = 'hs-row-buttons';

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

    public static function buttons(Table $table): Table
    {
        return $table
            ->recordActionsAlignment(Alignment::Center->value)
            ->extraAttributes(['class' => self::BUTTONS_CLASS], merge: true);
    }
}
