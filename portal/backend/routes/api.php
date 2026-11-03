<?php

use Illuminate\Support\Facades\Route;

//controller connections
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\CountyController;
use App\Http\Controllers\RelationshipController;
use App\Http\Controllers\ServiceFeedbackController;
use App\Http\Controllers\ResourceRequestFormController;
use App\Http\Controllers\ContentFileController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicNewsletterController;
use App\Http\Controllers\PublicCivilianAssessmentController;

/*
The whole JSON API is the narrow public surface below. Staff work happens in
the Filament panel, which talks to the database itself, so there are no
signed-in API routes: the per-table apiResources for assessments, submitter
info, officer assessments, agents and agencies (and GET /api/user) sat behind
auth:sanctum with nothing able to issue a token, and were removed in team
plan task 29. Don't add a staff route here - build the screen in the panel.
ApiSurfaceTest lists every /api route and fails on anything new.
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});

// Narrow public API surface for the website - its forms, the civilian
// assessment and the published content. Every route here goes through a
// restricted connection (FeedbackPublic, PortalPublic, ContentPublic - see
// the controllers), never a full-access one, and every write is throttled.
Route::prefix('public')->group(function () {
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/counties', [CountyController::class, 'index']);
    // The civilian assessment's "Relationship to victim" options - from
    // App\Enums\OffenderRelationship, no database involved.
    Route::get('/relationships', [RelationshipController::class, 'index']);
    Route::post('/service-feedback', [ServiceFeedbackController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/resource-requests', [ResourceRequestFormController::class, 'store'])
        ->middleware('throttle:10,1');

    // The civilian lethality assessment, in one request and one transaction,
    // through the INSERT-only PortalPublic connection.
    Route::post('/civilian-assessments', [PublicCivilianAssessmentController::class, 'store'])
        ->middleware('throttle:10,1');

    // Website content, managed in the staff panel. Published records only,
    // read through the restricted ContentPublic connection, and shaped to
    // match the mock JSON the Events & News page was built against.
    Route::get('/events', [PublicEventController::class, 'index'])
        ->name('public.events.index');
    Route::get('/newsletters', [PublicNewsletterController::class, 'index'])
        ->name('public.newsletters.index');

    // Event images and newsletter PDFs, served out of the database. Only
    // reachable while a published event or newsletter points at the file -
    // the controller enforces that, not this route.
    Route::get('/content-files/{file}', [ContentFileController::class, 'show'])
        ->name('public.content-files.show');
});