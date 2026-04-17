<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/portal/dashboard', function () {
    return view('portal.dashboard');
});

Route::get('/portal/assessment', function () {
    return view('portal.assessment');
});


// Puts answers into database
Route::post('/portal/assessment', function (Request $request) {
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

    return redirect('/portal/dashboard')
        ->with('success', 'Assessment submitted successfully.');
});