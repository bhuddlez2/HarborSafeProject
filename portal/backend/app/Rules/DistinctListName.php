<?php

namespace App\Rules;

use App\Support\ListNames;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/*
Refuses a name that is already on its list, or sounds like one that is
(App\Support\ListNames). Only a new or changed name is checked, so a row
whose name already sat next to a look-alike can still be saved untouched.

On a screen's form:
    ->rule(fn (?Model $record) => new DistinctListName(Model::class, 'Name', $record))
    ->dehydrateStateUsing(fn (?string $state) => ListNames::tidy($state))
*/
class DistinctListName implements ValidationRule
{
    /** @param class-string<Model> $model */
    public function __construct(
        private readonly string $model,
        private readonly string $column,
        private readonly ?Model $record = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($name = ListNames::tidy($value)) === '') {
            return;
        }

        if ($this->record !== null && ListNames::tidy((string) $this->record->getAttribute($this->column)) === $name) {
            return;
        }

        $others = $this->model::query()
            ->when($this->record, fn ($query) => $query->whereKeyNot($this->record->getKey()))
            ->pluck($this->column);

        $clash = ListNames::clashWith($name, $others);

        if ($clash === null) {
            return;
        }

        $fail(mb_strtolower(ListNames::tidy($clash)) === mb_strtolower($name)
            ? "\"{$clash}\" is already on the list."
            : "This sounds like \"{$clash}\", which is already on the list.");
    }
}
