<?php

use App\Models\PrivateAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//controller connections
use App\Http\Controllers\PrivateAssessmentController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\SubmitterInfoController;
use App\Http\Controllers\LawEnforcementAssessmentController;
use App\Http\Controllers\LawEnforcementAgentController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\CountyController;
use App\Http\Controllers\ServiceFeedbackController;
use App\Http\Controllers\ResourceRequestFormController;
use App\Http\Controllers\ContentFileController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); // "if (!token) return 401"

// This replaces: app.get('/', (req, res) => { ... })
Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});

// The anonymous civilian flow (portal/frontend/app/page.js) only ever
// creates records, so store is the one public action on these three tables.
// Reading, editing or deleting them - civilian PII included - needs auth.
Route::apiResource('/private-assessments', PrivateAssessmentController::class)->only('store');
Route::apiResource('/assessments', AssessmentController::class)->only('store');
Route::apiResource('/submitter-info', SubmitterInfoController::class)->only('store');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('/private-assessments', PrivateAssessmentController::class)->except('store');
    Route::apiResource('/assessments', AssessmentController::class)->except('store');
    Route::apiResource('/submitter-info', SubmitterInfoController::class)->except('store');

    // Officers submit through the staff panel's wizard now, not this API;
    // every action here needs an authenticated user.
    Route::apiResource('/law-enforcement-assessments', LawEnforcementAssessmentController::class);
    Route::apiResource('/law-enforcement-agents', LawEnforcementAgentController::class);
    Route::apiResource('/agencies', AgencyController::class);
});

// Narrow public API surface for the website's feedback/resource-request
// forms - reads and writes here go through the restricted FeedbackPublic
// connection (see the controllers), never the full-access Feedback one.
Route::prefix('public')->group(function () {
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/counties', [CountyController::class, 'index']);
    Route::post('/service-feedback', [ServiceFeedbackController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/resource-requests', [ResourceRequestFormController::class, 'store'])
        ->middleware('throttle:10,1');

    // Event images and newsletter PDFs, served out of the database. Only
    // reachable while a published event or newsletter points at the file -
    // the controller enforces that, not this route.
    Route::get('/content-files/{file}', [ContentFileController::class, 'show'])
        ->name('public.content-files.show');
});