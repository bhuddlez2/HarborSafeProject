<?php

namespace App\Filament\Concerns;

use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/*
A screen allows only the actions it lists; everything else is refused.

Filament asks one method, getAuthorizationResponse(), before every built-in
button (Create, View, Edit, Delete, bulk delete, restore, replicate,
reorder), and when a model has no policy it answers "allowed". So a screen
that only gated who could open it would quietly accept any Delete or Edit
button someone added later. With this trait the answer is no unless the
action is in allowedActions(), and only for someone who may open the screen
(canAccess(), which each screen using this trait must define itself).

Overriding canDelete() and friends is not enough: Filament's buttons skip
them and call getAuthorizationResponse() directly.

Agencies, Accounts and Officers follow the same rule written out by hand;
the two law-enforcement assessment screens follow LawEnforcementAssessmentPolicy,
which refuses every write but update(). ActionAccessTest pins every screen.
*/
trait AllowsOnlyListedActions
{
    /**
     * What this screen allows besides listing its rows: any of 'view',
     * 'create', 'update', 'delete', 'deleteAny'. Anything else - restore,
     * replicate, reorder, force delete - is never needed here.
     *
     * @return list<string>
     */
    abstract protected static function allowedActions(): array;

    // A further check on one row, for screens where who may act depends on
    // the record. By default every row the screen's query returns.
    protected static function allowsRecord(string $action, Model $record): bool
    {
        return true;
    }

    // A row the screen refuses for a reason staff should see: the button
    // stays, greyed out, with this as its hover text (an action opts in with
    // ->authorizationTooltip()). Null when the row is not refused this way.
    protected static function recordRefusal(string $action, Model $record): ?string
    {
        return null;
    }

    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = static::canAccess()
            && ($action === 'viewAny' || in_array($action, static::allowedActions(), true))
            && ($record === null || static::allowsRecord($action, $record));

        if ($allowed && $record !== null && filled($reason = static::recordRefusal($action, $record))) {
            return Response::deny($reason);
        }

        return $allowed ? Response::allow() : Response::deny();
    }
}
