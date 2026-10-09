<?php

namespace App\Filament\Resources\StaffAccounts;

use App\Enums\UserRole;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Models\User;
use App\Filament\Tables\TableAlignment;
use App\Support\Timezones;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

/*
Staff account set-up on the content-management side: create, edit and
deactivate accounts, with first and last name as separate fields.

Who may manage which accounts is decided in exactly one place,
manageableRoles(). Today that is admin -> secretaries, and nothing else
(Filament_CMS_Design.md section 5): admin never creates officers, police
admins or other admins. The planned super admin is one new line in that map;
police admins provision officers on their own screen (OfficerAccountResource),
which this resource does not touch.

Nothing is ever deleted - deactivating keeps the account's history - and the
role is never read from the request: it is forced on create and never written
on edit. Authorization lives here rather than in a UserPolicy for the same
reason OfficerAccountResource gives: the two screens need different rules for
the same model.
*/
class StaffAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'accounts';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'account';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | UnitEnum | null $navigationGroup = 'Content management';

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
            (bool) $user?->hasActiveRole(UserRole::Admin) => [UserRole::Secretary],
            default => [],
        };
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

        return $roles === []
            ? parent::getEloquentQuery()->whereRaw('1 = 0')
            : parent::getEloquentQuery()->whereIn('role', $roles);
    }

    // No role field: the create page forces it. `name` is not on the form
    // either - User::booted() builds it from the two parts.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->schema([
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
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive accounts cannot sign in. Nothing is deleted.')
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),

                Section::make('Password')
                    ->schema([
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
                    ])
                    ->columns(2),
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
                    ->formatStateUsing(fn (UserRole $state): string => str($state->value)->replace('_', ' ')->title())),
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
