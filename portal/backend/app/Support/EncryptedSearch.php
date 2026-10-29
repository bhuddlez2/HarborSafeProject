<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

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
        $search = trim($search);
        $model = $scope->getModel();
        $key = $model->getKeyName();

        $rows = (clone $scope)
            ->select(array_map(fn (string $column): string => $model->qualifyColumn($column), [$key, ...$columns]))
            ->cursor();

        $keys = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (self::contains($row->getAttribute($column), $search)) {
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
