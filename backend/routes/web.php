<?php

// web.php is used for the php/Blade-based activities

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

    echo("POST : /portal/assessment reached");  

    return redirect('/portal/dashboard')
        ->with('success', 'Assessment submitted successfully.');
});


// Saves basic information that has been inputted by the victim

//!! ALL IS PROOF OF CONCEPT, NOT WORKING
Route::post('/portal/dashboard', function (Request $request) {
    
    $submissionType = $request->input('submission_type');

    if ($submissionType === 'anonymous') {
        // YOUR CUSTOM LOGIC HERE
        // e.g. generate a case number, log without PII, etc.

        return redirect('/portal/assessment')
            ->with('success', 'Anonymous session started.');
    }

    // Full form submission
    $validated = $request->validate([
        'FirstName'   => 'required|string|max:255',
        'LastName'    => 'required|string|max:255',
        'DateOfBirth' => 'required|date',
        'PhoneNumber' => 'required|string|max:255',
        'Address'     => 'required|string|max:255'
    ]);

    // VictimInfo::create($validated);

    return redirect('/portal/assessment')
        ->with('success', 'Please complete the assessment');
});