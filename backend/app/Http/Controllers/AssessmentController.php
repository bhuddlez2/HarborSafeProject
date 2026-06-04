<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssessmentAnswers;

class AssessmentController extends Controller
{
    // GET - list all
    public function index()
    {
        return response()->json(
            AssessmentAnswers::all()
        );
    }

    // GET - get one
    public function show($uuid) 
    {
        return response()->json(AssessmentAnswers::findOrFail($uuid));
    }

    // PUT - update existing
    public function update(Request $request, $uuid)
    {
        $assessment = AssessmentAnswers::findOrFail($uuid);
        $assessment->update($request->validated());
        return response()->json($assessment);
    }

    // POST - create new
    public function store(Request $request)
    {
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

        AssessmentAnswers::create($validated);

        return redirect('/portal/dashboard')
            ->with('success', 'Assessment submitted successfully.');
    }

    // DELETE — delete
    public function destroy($uuid)
    {
        AssessmentAnswers::findOrFail($uuid)->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }
}
