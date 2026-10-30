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

  TextColumn::make('FirstName')
      ->searchable(query: EncryptedColumnSearch::query(ResourceRequestForm::class, ['FirstName', 'LastName', 'EmailAddress']))

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

    /** @param  class-string<Model>  $modelClass */
    public static function visible(string $modelClass): Builder
    {
        return $modelClass::query()->visibleTo(Filament::auth()->user());
    }
}
