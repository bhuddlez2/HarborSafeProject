<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

// Filament's Account page, with the single Name field replaced by first and
// last name - the two columns every account screen edits (see User::booted(),
// which rebuilds `name` from them). Everything else - email, password, saving -
// is inherited untouched, and the page still edits only the signed-in user's
// own details, never role or is_active.
class EditProfile extends BaseEditProfile
{
    protected function getNameFormComponent(): Component
    {
        return Grid::make(2)
            ->schema([
                TextInput::make('first_name')
                    ->label('First name')
                    ->required()
                    ->maxLength(User::NAME_PART_MAX)
                    ->autofocus(),
                TextInput::make('last_name')
                    ->label('Last name')
                    ->required()
                    ->maxLength(User::NAME_PART_MAX),
            ]);
    }
}
