<?php

namespace App\Filament\Resources\Agencies;

use App\Enums\UserRole;
use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Models\Agency;
use App\Rules\DistinctListName;
use App\Support\ListNames;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/*
The police agencies officers and police admins belong to (team plan task 15).
Super admin only: it adds agencies and renames them, and then creates each
agency's police admin on Accounts.

Never deleted, and there is no deactivate (settled 2026-10-21): accounts and,
through them, assessments point at agencies, and a police admin's whole view
is scoped by one (LawEnforcementAssessment::visibleTo()). Renaming is safe -
everything stores the id. To stop an agency's people signing in, deactivate
their accounts.

Authorization lives here, like the account screens: getAuthorizationResponse()
below allows only what it lists.
*/
class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static ?string $slug = 'agencies';

    protected static ?string $navigationLabel = 'Agencies';

    protected static ?string $modelLabel = 'agency';

    protected static ?string $pluralModelLabel = 'agencies';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string | UnitEnum | null $navigationGroup = 'Content Management';

    // Straight after Accounts.
    protected static ?int $navigationSort = 5;

    // Every check ends up here. Anything not listed - delete, restore,
    // replicate, reorder - is refused.
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $allowed = match ($action) {
            'viewAny', 'create', 'view', 'update' => (bool) Filament::auth()->user()?->hasActiveRole(UserRole::SuperAdmin),
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

    // How many of each agency's accounts are police admins and officers,
    // active or not.
    public static function getEloquentQuery(): Builder
    {
        $countRole = fn (UserRole $role): \Closure => fn (Builder $agents): Builder => $agents->whereHas(
            'user',
            fn (Builder $user): Builder => $user->where('role', $role->value),
        );

        return parent::getEloquentQuery()->withCount([
            'agents as police_admins_count' => $countRole(UserRole::PoliceAdmin),
            'agents as officers_count' => $countRole(UserRole::LawEnforcement),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Agency name')
                    ->required()
                    ->maxLength(255)
                    // No duplicates or sound-alikes on the list (B55/B60).
                    ->rule(fn (?Model $record): DistinctListName => new DistinctListName(Agency::class, 'name', $record))
                    ->dehydrateStateUsing(fn (?string $state): ?string => ListNames::tidy($state))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Search by Name')
            ->columns([
                TextColumn::make('name')
                    ->label('Agency')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('police_admins_count')
                    ->label('Police admins')
                    ->sortable(),
                TextColumn::make('officers_count')
                    ->label('Officers')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->label('Rename'),
            ])
            ->emptyStateHeading('No agencies yet')
            ->emptyStateDescription('Add an agency, then create its police admin on Accounts.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAgencies::route('/'),
        ];
    }
}
