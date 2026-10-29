<?php

namespace App\Filament\Tables;

use App\Support\EncryptedSearch;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
Table search on encrypted columns (App\Support\SensitiveData): the
database can't LIKE ciphertext, so a column's search box goes through
App\Support\EncryptedSearch instead, over the rows the signed-in user may see
($modelClass::visibleTo()):

  TextColumn::make('VictimLastName')
      ->searchable(query: EncryptedColumnSearch::query(LawEnforcementAssessment::class, ['VictimFirstName', 'VictimLastName']))

Never ->searchable(['SomeEncryptedColumn']): it would search ciphertext and
quietly find nothing.
*/
final class EncryptedColumnSearch
{
    /**
     * @param  class-string<Model>  $modelClass  a model with a visibleTo() scope
     * @param  list<string>  $columns
     */
    public static function query(string $modelClass, array $columns): Closure
    {
        return fn (Builder $query, string $search): Builder => EncryptedSearch::constrain(
            $query,
            self::visible($modelClass),
            $columns,
            $search,
        );
    }

    /**
     * Like query(), matched against texts built from the columns rather than
     * each column alone - a full "First Last" name, a relationship's label
     * (EncryptedSearch::keysMatching()). Same scope: the viewer's own rows.
     *
     * @param  class-string<Model>  $modelClass  a model with a visibleTo() scope
     * @param  list<string>  $columns
     * @param  Closure(Model): list<?string>  $texts
     */
    public static function matching(string $modelClass, array $columns, Closure $texts): Closure
    {
        return fn (Builder $query, string $search): Builder => $query->whereIn(
            $query->getModel()->getQualifiedKeyName(),
            EncryptedSearch::keysMatching(self::visible($modelClass), $columns, $texts, $search),
        );
    }

    /** @param  class-string<Model>  $modelClass */
    public static function visible(string $modelClass): Builder
    {
        return $modelClass::query()->visibleTo(Filament::auth()->user());
    }
}
