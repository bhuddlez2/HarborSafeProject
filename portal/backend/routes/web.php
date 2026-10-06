<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentFileController;
use App\Http\Middleware\EnsureStaffPanelAccess;

/*
Upload previews and downloads inside the staff panel.

The public endpoint in routes/api.php only serves files that a PUBLISHED event
or newsletter points at, so it cannot preview a draft's image - which is most
of what the panel does. This route covers that: same bytes, authorization by
panel access instead of by publication.

It sits in web.php rather than api.php because it needs the session cookie the
panel already sets; the api middleware group is stateless.
*/
// EnsureStaffPanelAccess does the authentication check itself - deliberately,
// since Laravel's `auth` would redirect to a route named `login` that this
// application does not define. See that class for the detail.
Route::middleware([
    'web',
    EnsureStaffPanelAccess::class,
])->group(function () {
    Route::get('/files/{file}', [ContentFileController::class, 'showStaff'])
        ->name('staff.content-files.show');
});

/*
The panel moved from /staff to the site root (sign-in is /login). Old
bookmarks and links - /staff, /staff/login, /staff/police/assessments - land
on the same page at its new address rather than a 404.
*/
Route::get('/staff/{path?}', fn (?string $path = null) => redirect('/'.($path ?? '')))
    ->where('path', '.*');
