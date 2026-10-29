<?php

namespace App\Filament\Tables;

use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/*
Tables whose rows become cards on a phone, and whose row buttons stack in a
compact column between a phone and a full-width monitor (Finishing Touches
item 9): the three assessment tables (through AssessmentColumns), Service
feedback, Resource requests and Follow-ups. The layout is theme.css's
"Record cards" section.

apply() turns it on for a table. Each column then takes one role, which
decides where it sits on the card:

  title()   the card's heading, bold, top left, no label (a name, a service)
  corner()  top right, no label (a badge, a form type)
  body()    full width, no label (a comment)
  (none)    a labelled line: small label on the left, value beside it
  meta()    a labelled line, after every other one (dates)

then the row buttons along the bottom. A bulk-select checkbox, where a table
has one, sits top left. The roles are cell classes, so they change nothing
on a desktop table.
*/
final class RecordCards
{
    public const ROW = 'hs-card';

    public static function apply(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->recordClasses(self::ROW);
    }

    public static function title(Column $column): Column
    {
        return self::role($column, 'title');
    }

    public static function corner(Column $column): Column
    {
        return self::role($column, 'corner');
    }

    public static function body(Column $column): Column
    {
        return self::role($column, 'body');
    }

    public static function meta(Column $column): Column
    {
        return self::role($column, 'meta');
    }

    // The small "Staff" tag shown under the date of a form staff entered
    // themselves (Service feedback, Resource requests). Fixed text only.
    public static function staffTag(): HtmlString
    {
        return new HtmlString('<span class="hs-staff-tag">Staff</span>');
    }

    // An email address that may wrap only at its "@" and dots, so a narrow
    // column shows "linda.fairchild@" / "example.test" rather than breaking
    // mid-word or pushing the table wider than the screen. Escaped first:
    // the address is what the public typed.
    public static function email(?string $address): ?HtmlString
    {
        if (blank($address)) {
            return null;
        }

        return new HtmlString(str_replace(['@', '.'], ['<wbr>@', '.<wbr>'], e($address)));
    }

    private static function role(Column $column, string $role): Column
    {
        return $column->extraCellAttributes(['class' => self::ROW.'-'.$role], merge: true);
    }
}
