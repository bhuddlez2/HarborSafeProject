<?php

namespace App\Filament\Resources\StaffAccounts;

use App\Enums\UserRole;
use App\Filament\Forms\AccountFormSections;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Filament\Tables\TableAlignment;
use App\Models\Agency;
use App\Models\User;
use App\Support\Timezones;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/*
Staff account set-up on the content-management side: create, edit and
deactivate accounts, with first and last name as separate fields.

Who may manage which accounts is decided in exactly one place,
manageableRoles() (Filament_CMS_Design.md section 5): admin -> secretaries
only, never officers, police admins or other admins; super admin -> every
role, other super admins included, so ownership can pass on when one leaves
(`php artisan staff:create-super-admin` makes only the first). Nobody manages
their own account here, so the super admin doing the deactivating always
remains: the last active one can't be switched off. Police admins provision
their own agency's officers on their own screen (OfficerAccountResource),
which this resource does not touch.

A creator with one manageable role sees it greyed out: the role is forced. A
creator with several (super admin) picks one, and the create page checks the
pick against manageableRoles() again on the server. Either way the role is
never written on edit. Police admins and officers also get an agency, and
officers a badge number, both on their law_enforcement_agents row. The agency
is fixed once set: LawEnforcementAssessment::visibleTo() follows the
submitter's current agency, so moving someone would hand their old
assessments to the new agency's police admin. To move a person, deactivate
the account and create a new one.

Nothing is ever deleted - deactivating keeps the account's history.
Authorization lives here rather than in a UserPolicy for the same reason
OfficerAccountResource gives: the two screens need different rules for the
same model.
*/
class StaffAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'accounts';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'account';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | UnitEnum | null $navigationGroup = 'Content Management';

    // After the three content clusters.
    protected static ?int $navigationSort = 4;

    /**
     * The roles the signed-in user may create and manage. Empty for anyone who
     * manages no accounts here, which closes the whole resource to them.
     *
     * @return list<UserRole>
     */
    public static function manageableRoles(): array
    {
        $user = Filament::auth()->user();

        return match (true) {
            (bool) $user?->hasActiveRole(UserRole::SuperAdmin) => [
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::Secretary,
                UserRole::PoliceAdmin,
                UserRole::LawEnforcement,
            ],
            (bool) $user?->hasActiveRole(UserRole::Admin) => [UserRole::Secretary],
            default => [],
        };
    }

    // Whether the signed-in user picks a role (and sees roles and agencies in
    // the list), rather than having the one role they manage forced.
    public static function choosesRole(): bool
    {
        return count(static::manageableRoles()) > 1;
    }

    /** @return array<string, string> role value => label */
    public static function roleOptions(): array
    {
        return collect(static::manageableRoles())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function agencyOptions(): array
    {
        return Agency::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    // The role the agency and badge fields follow: the account's own on
    // edit, the forced one for a creator with a single role, and the one
    // picked otherwise. Only that last case reads the form, so a tampered
    // role can't hide or show fields.
    protected static function formRole(Get $get, ?Model $record): ?UserRole
    {
        if ($record instanceof User) {
            return UserRole::tryFrom((string) ($record->getAttributes()['role'] ?? ''));
        }

        if (! static::choosesRole()) {
            return static::manageableRoles()[0] ?? null;
        }

        return UserRole::tryFrom((string) $get('role'));
    }

    // The one place every check ends up, as in OfficerAccountResource.
    // Anything not listed - delete, restore, replicate, reorder - is refused.
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $allowed = match ($action) {
            'viewAny', 'create' => static::manageableRoles() !== [],
            'view', 'update' => $record instanceof User && static::manages($record),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function canViewAny(): bool
    {
        return static::getAuthorizationResponse('viewAny')->allowed();
    }

    public static function canCreate(): bool
    {
        return static::getAuthorizationResponse('create')->allowed();
    }

    public static function canView(Model $record): bool
    {
        return static::getAuthorizationResponse('view', $record)->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::getAuthorizationResponse('update', $record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    // Raw role string, so an unrecognised value matches nothing. Never your
    // own account: nobody deactivates or re-roles themselves from here.
    protected static function manages(User $record): bool
    {
        $role = UserRole::tryFrom((string) ($record->getAttributes()['role'] ?? ''));

        return $role !== null
            && in_array($role, static::manageableRoles(), true)
            && $record->getKey() !== Filament::auth()->id();
    }

    // Scopes the list, and the record lookup behind the edit page, so an
    // account outside the user's manageable roles is a 404.
    public static function getEloquentQuery(): Builder
    {
        $roles = array_map(fn (UserRole $role): string => $role->value, static::manageableRoles());

        // Never your own account, even when your role is one you manage
        // (super admin): it is a 404 here, as on every account screen.
        return $roles === []
            ? parent::getEloquentQuery()->whereRaw('1 = 0')
            : parent::getEloquentQuery()->whereIn('role', $roles)->whereKeyNot(Filament::auth()->id());
    }

    // Laid out by AccountFormSections: Role | Email, First name | Last name,
    // then agency and badge as the role needs them, then Password. The role
    // is a dropdown only for a creator with several to pick from (super
    // admin) on create; otherwise it is the read-only fixedRole() box - the
    // forced role, or the account's own on edit - which is never sent.
    // `name` is not on the form - User::booted() builds it from the two parts.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AccountFormSections::details([
                    Select::make('role')
                        ->label('Role')
                        ->options(fn (): array => static::roleOptions())
                        ->required()
                        ->live()
                        ->visible(fn (string $operation): bool => $operation === 'create' && static::choosesRole()),
                    AccountFormSections::fixedRole(
                        fn (Get $get, ?Model $record): ?UserRole => static::formRole($get, $record),
                    )
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? "A role can't be changed. To change it, deactivate this account and create a new one."
                            : null)
                        ->visible(fn (string $operation): bool => $operation === 'edit' || ! static::choosesRole()),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(User::NAME_PART_MAX),
                    TextInput::make('last_name')
                        ->label('Last name')
                        ->required()
                        ->maxLength(User::NAME_PART_MAX),
                    Select::make('agency_id')
                        ->label('Agency')
                        ->options(fn (): array => static::agencyOptions())
                        ->searchable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? "An agency can't be changed, because this person's assessments would move with them. To move them, deactivate this account and create a new one."
                            : null)
                        ->visible(fn (Get $get, ?Model $record): bool => (bool) static::formRole($get, $record)?->needsAgency()),
                    TextInput::make('badge_number')
                        ->label('Badge number')
                        ->required()
                        ->maxLength(255)
                        ->visible(fn (Get $get, ?Model $record): bool => static::formRole($get, $record) === UserRole::LawEnforcement),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Inactive accounts cannot sign in. Nothing is deleted.')
                        ->visibleOn('edit')
                        ->columnSpanFull(),
                ]),
                AccountFormSections::password(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('First name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TableAlignment::symbol(TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())),
                // Police admins and officers; a dash for everyone else.
                TextColumn::make('lawEnforcementAgent.agency.name')
                    ->label('Agency')
                    ->placeholder('—')
                    ->visible(fn (): bool => static::choosesRole()),
                TableAlignment::symbol(IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()),
                // A moment, not a calendar date: shown as the Eastern day it
                // happened on (App\Support\Timezones).
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->timezone(Timezones::DISPLAY)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(fn (): array => static::roleOptions())
                    ->visible(fn (): bool => static::choosesRole()),
                SelectFilter::make('agency')
                    ->label('Agency')
                    ->options(fn (): array => static::agencyOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('lawEnforcementAgent', fn (Builder $agent): Builder => $agent->where('agency_id', $data['value']))
                        : $query)
                    ->visible(fn (): bool => static::choosesRole()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->defaultSort('last_name')
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No accounts yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffAccounts::route('/'),
            'create' => CreateStaffAccount::route('/create'),
            'edit' => EditStaffAccount::route('/{record}/edit'),
        ];
    }
}
