<?php

namespace App\Filament\Assessments;

use App\Enums\OffenderRelationship;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/*
The "Relationship to victim" pair, shared by the New Assessment wizard and the
officer's Edit pop-up so both offer exactly the same thing: a dropdown from
App\Enums\OffenderRelationship, plus a "Please specify" box that appears -
and becomes required - only when Other is picked.

The box always saves: its typed text for Other, NULL for anything else. So
switching an existing record from Other to, say, Spouse clears the old detail
rather than leaving it behind in the record (and in the change log).
*/
final class RelationshipFields
{
    /** @return array{0: Select, 1: TextInput} */
    public static function make(): array
    {
        return [
            Select::make('OffenderVictimRelationship')
                ->label('Relationship to victim')
                ->native()
                ->options(OffenderRelationship::options())
                ->placeholder('Select')
                ->required()
                ->live()
                ->validationMessages(['required' => 'Select an option']),

            TextInput::make('OffenderVictimRelationshipOther')
                ->label('Please specify')
                ->maxLength(50)
                ->visible(fn (Get $get): bool => self::isOther($get))
                ->required(fn (Get $get): bool => self::isOther($get))
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => self::isOther($get) && filled($state)
                    ? trim($state)
                    : null)
                ->validationMessages(['required' => 'Required']),
        ];
    }

    private static function isOther(Get $get): bool
    {
        return $get('OffenderVictimRelationship') === OffenderRelationship::Other->value;
    }
}
