<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); // "if (!token) return 401"

// This replaces: app.get('/', (req, res) => { ... })
Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});


// -------------------------------------- \\
//
//   Routes for:     tblAssessmentAnswers
//  
// -------------------------------------- //

// POST answers into the tblAssessmentAnswers table
Route::post('/assessment-answers', function (Request $request) {
    $validated = $request->validate([
        'RiskIndicator1'  => 'required|boolean',
        'RiskIndicator2'  => 'required|boolean',
        'RiskIndicator3'  => 'required|boolean',
        'RiskIndicator4'  => 'required|boolean',
        'RiskIndicator5'  => 'required|boolean',
        'RiskIndicator6'  => 'required|boolean',
        'RiskIndicator7'  => 'required|boolean',
        'RiskIndicator8'  => 'required|boolean',
        'RiskIndicator9'  => 'required|boolean',
        'RiskIndicator10' => 'required|boolean',
        'RiskIndicator11' => 'required|boolean',
    ]);

    $validated['AssessmentDocID'] = \Illuminate\Support\Str::uuid();

    // DB::table('tblAssessmentAnswers')->insert($validated);

    return response()->json([
        'message'         => 'Assessment saved',
        'AssessmentDocID' => $validated['AssessmentDocID']
    ], 201);
})->middleware('throttle:100,15');