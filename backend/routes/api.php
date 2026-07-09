<?php

use App\Models\PrivateAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//controller connections
use App\Http\Controllers\PrivateAssessmentController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\SubmitterInfoController;

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