<?php

namespace App\Filament\Resources\OfficerAccounts;

use App\Enums\UserRole;
use App\Filament\PoliceAdminNavigation;
use App\Filament\Resources\OfficerAccounts\Pages\CreateOfficerAccount;
use App\Filament\Resources\OfficerAccounts\Pages\EditOfficerAccount;
use App\Filament\Resources\OfficerAccounts\Pages\ListOfficerAccounts;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

// Officer provisioning: a police admin creates, edits and deactivates the
// law_enforcement accounts in their own agency (User::managedAgencyId()).
// Nobody else gets in - not admins (Filament_CMS_Design.md §5 rule 2) - and
// nothing is ever deleted; deactivating keeps the assessment history.
//
// Authorization lives here rather than in a UserPolicy, because a future
// admin "Users" screen needs different rules for the same model. Listed in
// resources.exclude in config/filament-shield.php for the same reason.
class OfficerAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'police/officers';

    protected static ?string $navigationLabel = 'Officers';

    protected static ?string $modelLabel = 'officer';

    protected static ?string $pluralModelLabel = 'officers';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    // Fourth in the police admin's sidebar - see App\Filament\PoliceAdminNavigation.
    protected static string | UnitEnum | null $navigationGroup = PoliceAdminNavigation::GROUP;

    protected static ?int $navigationSort = PoliceAdminNavigation::OFFICERS;

    // The one place every check ends up: the can*() methods below, and the
    // table and page actions, which ask for the response directly. Anything
    // not listed - delete, restore, replicate, reorder - is refused.
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $agencyId = Filament::auth()->user()?->managedAgencyId();

        $allowed = match ($action) {
            'viewAny', 'create' => $agencyId !== null,
            'view', 'update' => $agencyId !== null
                && $record instanceof User
                && static::isOfficerIn($record, $agencyId),
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

    // Raw role string, so a police admin, admin or secretary is never treated
    // as an officer and an unrecognised value matches nothing.
    protected static function isOfficerIn(User $record, int $agencyId): bool
    {
        return ($record->getAttributes()['role'] ?? null) === UserRole::LawEnforcement->value
            && $record->agencyId() === $agencyId;
    }

    // Scopes the list, and the record lookup behind the edit page, to the
    // officers of the police admin's own agency - so another agency's
    // officer, or any non-officer account, is a 404. Everyone else matches
    // nothing.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $agencyId = Filament::auth()->user()?->managedAgencyId();

        if ($agencyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('role', UserRole::LawEnforcement->value)
            ->whereHas('lawEnforcementAgent', fn (Builder $agent): Builder => $agent->where('agency_id', $agencyId));
    }

    // No role or agency field: both are forced on save by the pages.
    // badge_number lives on law_enforcement_agents, so the pages read and
    // write it themselves.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('First name')
                    ->required()
                    ->maxLength(User::NAME_PART_MAX),
                TextInput::make('last_name')
                    ->label('Last name')
                    ->required()
                    ->maxLength(User::NAME_PART_MAX),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('badge_number')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive officers cannot sign in. Their assessments are kept.')
                    ->visibleOn('edit'),
                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'edit' ? 'New password' : 'Password')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null)
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(Password::min(15)->max(64)->uncompromised())
                    ->same('passwordConfirmation')
                    ->dehydrated(fn ($state): bool => filled($state)),
                TextInput::make('passwordConfirmation')
                    ->label('Confirm password')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    // Required on create, where the password is. On edit only once a new
                    // password is typed, so it must not say "Optional" either - see
                    // App\Filament\Forms\RequirementMarkers.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->requiredWith('password')
                    ->placeholder(null)
                    ->dehydrated(false),
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
                TextColumn::make('lawEnforcementAgent.badge_number')
                    ->label('Badge number')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->defaultSort('last_name')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfficerAccounts::route('/'),
            'create' => CreateOfficerAccount::route('/create'),
            'edit' => EditOfficerAccount::route('/{record}/edit'),
        ];
    }
}
