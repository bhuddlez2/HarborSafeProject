<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

// Filament's Account page, with the single Name field replaced by first and
// last name - the two columns every account screen edits (see User::booted(),
// which rebuilds `name` from them). Email, password and the rest are
// inherited, and the page edits only the signed-in user's own details, never
// role or is_active.
//
// Officers also change their own badge number here. It lives on
// law_enforcement_agents, so this page reads and writes it itself, the way
// the Officers screen (EditOfficerAccount) does. The agency is never editable.
class EditProfile extends BaseEditProfile
{
    // law_enforcement_agents.badge_number is a varchar(255).
    private const BADGE_MAX = 255;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getBadgeNumberFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

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

    // Officers only. Hidden for everyone else, which also keeps it out of
    // the saved data.
    protected function getBadgeNumberFormComponent(): Component
    {
        $trim = fn (mixed $state): mixed => is_string($state) ? trim($state) : $state;

        return TextInput::make('badge_number')
            ->label('Badge number')
            ->required()
            ->maxLength(self::BADGE_MAX)
            ->mutateStateForValidationUsing($trim)
            ->dehydrateStateUsing($trim)
            ->visible(fn (): bool => $this->canEditBadge());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->canEditBadge()) {
            $data['badge_number'] = $this->ownAgentRow()?->badge_number;
        }

        return $data;
    }

    // The profile and the badge in one Portal transaction. Filament's own
    // transaction would open on the default connection, and only when the
    // panel enables them, so it is not relied on here.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Never part of the users update.
        $badge = Arr::pull($data, 'badge_number');

        return DB::connection('Portal')->transaction(function () use ($record, $data, $badge): Model {
            $record = parent::handleRecordUpdate($record, $data);

            if ($this->canEditBadge() && filled($badge)) {
                // Only the badge, only on the signed-in user's own row.
                // law_enforcement_agents has no created_at/updated_at columns.
                LawEnforcementAgent::withoutTimestamps(
                    fn (): int => LawEnforcementAgent::whereKey($this->getUser()->getKey())
                        ->update(['badge_number' => $badge]),
                );
            }

            return $record;
        });
    }

    // Active officers with a law_enforcement_agents row. Police admins have
    // a row too, but only to carry their agency, so they get no badge field.
    private function canEditBadge(): bool
    {
        $user = $this->getUser();

        return $user instanceof User
            && $user->hasActiveRole(UserRole::LawEnforcement)
            && $this->ownAgentRow() !== null;
    }

    private function ownAgentRow(): ?LawEnforcementAgent
    {
        return LawEnforcementAgent::whereKey($this->getUser()->getKey())->first();
    }
}
