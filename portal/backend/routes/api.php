<?php

use App\Models\PrivateAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//controller connections
use App\Http\Controllers\PrivateAssessmentController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\SubmitterInfoController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\CountyController;
use App\Http\Controllers\ServiceFeedbackController;
use App\Http\Controllers\ResourceRequestFormController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); // "if (!token) return 401"

// This replaces: app.get('/', (req, res) => { ... })
Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});

// Routes for the private assessment table
Route::apiResource('/private-assessments', PrivateAssessmentController::class);

// Routes for lethality assessment table
Route::apiResource('/assessments', AssessmentController::class);

// Routes for submitter info table
Route::apiResource('/submitter-info', SubmitterInfoController::class);

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
});