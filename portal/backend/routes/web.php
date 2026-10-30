<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssessmentPdfController;
use App\Http\Controllers\CivilianAssessmentPdfController;
use App\Http\Controllers\ContentFileController;
use App\Http\Controllers\FeedbackScanController;
use App\Http\Controllers\FollowUpPdfController;
use App\Http\Controllers\KeepSessionAliveController;
use App\Http\Controllers\ResourceRequestPdfController;
use App\Http\Controllers\SubmissionPdfController;
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

    Route::get('/assessments/{id}/pdf', AssessmentPdfController::class)
        ->name('staff.assessments.pdf');

    // Civilian assessments: admins only (PrivateAssessment::visibleTo()).
    // Every download recorded (assessment_access_log).
    Route::get('/civilian-assessments/{id}/pdf', CivilianAssessmentPdfController::class)
        ->name('staff.civilian-assessments.pdf');

    // Service feedback: admins and secretaries; the form only, not logged.
    Route::get('/submissions/service-feedback/{id}/pdf', [SubmissionPdfController::class, 'serviceFeedback'])
        ->name('staff.service-feedback.pdf');

    // Resource requests: admins and secretaries (ResourceRequestForm::visibleTo()).
    // Every download recorded (assessment_access_log).
    Route::get('/submissions/resource-requests/{id}/pdf', ResourceRequestPdfController::class)
        ->name('staff.resource-requests.pdf');

    // A form plus its follow-up Log, from the follow-up page only: those who
    // work that form (FollowUp::visibleTo()). Every download recorded.
    Route::get('/follow-ups/{id}/pdf', FollowUpPdfController::class)
        ->name('staff.follow-ups.pdf-with-log');

    // Scans of paper feedback forms: admins and secretaries only, never
    // cached - see FeedbackScanController for why not /files/{file}.
    Route::get('/feedback-scans/{file}', [FeedbackScanController::class, 'show'])
        ->name('staff.feedback-scans.show');

    // "Stay signed in" on the idle-logout warning: restarts the session's
    // idle clock. Called only by that button, never on a timer.
    Route::post('/session/keep-alive', KeepSessionAliveController::class)
        ->name('staff.session.keep-alive');
});

/*
The panel moved from /staff to the site root (sign-in is /login). Old
bookmarks and links - /staff, /staff/login, /staff/police/assessments - land
on the same page at its new address rather than a 404.
*/
Route::get('/staff/{path?}', fn (?string $path = null) => redirect('/'.($path ?? '')))
    ->where('path', '.*');

/*
The Access log and the Change log are one list now, the Logs page
(App\Filament\Resources\Logs\LogResource, /logs). Old links - the two tabs
it once had and the addresses before them, with any sub-path - land on /logs,
keeping the query string. The page still checks who may open it.
*/
foreach (['logs/access', 'logs/changes', 'access-log', 'assessment-changes'] as $old) {
    Route::get("/{$old}/{path?}", function (Illuminate\Http\Request $request) {
        $query = $request->getQueryString();

        return redirect('/logs'.($query === null ? '' : '?'.$query));
    })->where('path', '.*');
}
