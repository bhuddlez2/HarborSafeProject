<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use App\Filament\Forms\AccountFormSections;
use App\Models\LawEnforcementAgent;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

// Filament's Account page, locked down for every role. Only two things can be
// changed here:
//
//   - your own password (every role), under the same rule as account
//     creation (AccountFormSections::passwordRule());
//   - your own badge number (active officers only). It lives on
//     law_enforcement_agents, so this page reads and writes it itself, the
//     way the Officers screen (EditOfficerAccount) does.
//
// First name, last name and email are shown greyed out and never saved: the
// person who manages the account changes them (Officers or Accounts). The save
// is built from that whitelist, so a tampered request carrying a name, email,
// role, agency_id, user_id or is_active changes nothing.
class EditProfile extends BaseEditProfile
{
    // law_enforcement_agents.badge_number is a varchar(255).
    private const BADGE_MAX = 255;

    // Every label above its box, so every box starts at the left edge.
    // Filament puts labels beside the boxes on a full-page Account page,
    // which left the two name boxes out of line with the rest.
    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getBadgeNumberFormComponent(),
                $this->getEmailFormComponent(),
                // Filament's own password boxes have no upper limit; without
                // these a password could be set that sign-in won't accept.
                $this->getPasswordFormComponent()->maxLength(User::PASSWORD_MAX),
                $this->getPasswordConfirmationFormComponent()->maxLength(User::PASSWORD_MAX),
                $this->getCurrentPasswordFormComponent()->maxLength(User::PASSWORD_MAX),
            ]);
    }

    // Read-only, with no validation rules, so a tampered value can neither
    // be saved nor trip a rule.
    protected function getNameFormComponent(): Component
    {
        return Grid::make(2)
            ->schema([
                $this->readOnly(TextInput::make('first_name')->label('First name')),
                $this->readOnly(TextInput::make('last_name')->label('Last name')),
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return $this->readOnly(
            TextInput::make('email')->label(__('filament-panels::auth/pages/edit-profile.form.email.label')),
        );
    }

    // Filament's field, with the account-creation rule in place of
    // Password::default().
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password.validation_attribute'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(AccountFormSections::passwordRule())
            ->helperText(AccountFormSections::PASSWORD_HINT)
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
            ->dehydrateStateUsing(fn (#[SensitiveParameter] $state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    // Asked for a new password only. Filament also asks when the email
    // differs from the stored one, which can now happen only in a tampered
    // request.
    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
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

    // Only what the page shows.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $fill = Arr::only($data, ['first_name', 'last_name', 'email']);

        if ($this->canEditBadge()) {
            $fill['badge_number'] = $this->ownAgentRow()?->badge_number;
        }

        return $fill;
    }

    // The whitelist: the password when one was typed, and an officer's own
    // badge, in one Portal transaction. Filament's own transaction would open
    // on the default connection, and only when the panel enables them, so it
    // is not relied on here. Filament's handleRecordUpdate() is not called: it
    // writes everything in $data.
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $password = $data['password'] ?? null;
        $badge = $data['badge_number'] ?? null;

        return DB::connection('Portal')->transaction(function () use ($record, $password, $badge): Model {
            if (filled($password)) {
                $record->update(['password' => $password]);
            }

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

    private function readOnly(TextInput $field): TextInput
    {
        return $field->disabled()->dehydrated(false);
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
