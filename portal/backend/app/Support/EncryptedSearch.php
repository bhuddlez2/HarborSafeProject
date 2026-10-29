<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
Search and filtering on encrypted columns (App\Support\SensitiveData).

The database only holds ciphertext, so it can't run LIKE on these columns.
Instead the rows the viewer may see ($scope - always a visibleTo() query) are
read with just their key and the searched columns, decrypted here, and matched
case-insensitively as "contains"; the table query is then narrowed to those
keys. Nothing decrypted leaves this class except the matching keys.

Cost grows with the number of visible rows: fine for thousands, slow for
hundreds of thousands.
*/
final class EncryptedSearch
{
    // Narrows $query to rows of $scope whose $columns contain $search. With
    // $relationship, $query's rows are kept when the related record matches
    // (e.g. a log entry by its assessment's names).
    public static function constrain(Builder $query, Builder $scope, array $columns, string $search, ?string $relationship = null): Builder
    {
        $keys = self::keys($scope, $columns, $search);

        if ($relationship === null) {
            return $query->whereIn($query->getModel()->getQualifiedKeyName(), $keys);
        }

        return $query->whereHas($relationship, fn (Builder $related): Builder => $related->whereIn($related->getModel()->getQualifiedKeyName(), $keys));
    }

    /** @return list<string|int> keys of the $scope rows where any column contains $search */
    public static function keys(Builder $scope, array $columns, string $search): array
    {
        return self::keysMatching(
            $scope,
            $columns,
            fn ($row): array => array_map(fn (string $column): ?string => $row->getAttribute($column), $columns),
            $search,
        );
    }

    /**
     * Keys of the $scope rows where any of the texts $texts($row) returns
     * contains $search - for a search over values built from the columns, such
     * as a full name ("First Last") or a relationship's label. Only $columns
     * (and the key) are read and decrypted. Runs of spaces in $search count as
     * one, and % and _ are ordinary characters.
     *
     * @param  list<string>  $columns
     * @param  Closure(Model): list<?string>  $texts
     * @return list<string|int>
     */
    public static function keysMatching(Builder $scope, array $columns, Closure $texts, string $search): array
    {
        $search = preg_replace('/\s+/u', ' ', trim($search));
        $model = $scope->getModel();
        $key = $model->getKeyName();

        $rows = (clone $scope)
            ->select(array_map(fn (string $column): string => $model->qualifyColumn($column), [$key, ...$columns]))
            ->cursor();

        $keys = [];

        foreach ($rows as $row) {
            foreach ($texts($row) as $text) {
                if (self::contains($text, $search)) {
                    $keys[] = $row->getKey();
                    break;
                }
            }
        }

        return $keys;
    }

    public static function contains(?string $value, string $search): bool
    {
        return $search === '' || ($value !== null && mb_stripos($value, $search) !== false);
    }
}
