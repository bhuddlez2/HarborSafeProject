<?php

// api.php is only used for any frontend interactions with the database

use App\Models\PrivateAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//controller connections
use App\Http\Controllers\PrivateAssessmentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); // "if (!token) return 401"

// This replaces: app.get('/', (req, res) => { ... })
Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});

//Routes for the private assessment tabel
Route::get('/assessments', [PrivateAssessmentController::class, 'index']);
Route::get('/assessments/{uuid}', [PrivateAssessmentController::class, 'show']);
Route::post('/assessments', [PrivateAssessmentController::class, 'store']);
Route::put('/assessments/{uuid}', [PrivateAssessmentController::class, 'update']);
Route::delete('/assessments/{uuid}', [PrivateAssessmentController::class, 'destroy']);
