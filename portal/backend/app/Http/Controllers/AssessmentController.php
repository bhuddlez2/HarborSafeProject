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
        $assessment = AssessmentAnswers::find($uuid);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        return response()->json($assessment);
    }

    // PUT - update existing
    public function update(Request $request, $uuid)
    {
        $assessment = AssessmentAnswers::find($uuid);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        $validated = $request->validate([
            'RiskIndicator1'  => 'sometimes|boolean',
            'RiskIndicator2'  => 'sometimes|boolean',
            'RiskIndicator3'  => 'sometimes|boolean',
            'RiskIndicator4'  => 'sometimes|boolean',
            'RiskIndicator5'  => 'sometimes|boolean',
            'RiskIndicator6'  => 'sometimes|boolean',
            'RiskIndicator7'  => 'sometimes|boolean',
            'RiskIndicator8'  => 'sometimes|boolean',
            'RiskIndicator9'  => 'sometimes|boolean',
            'RiskIndicator10' => 'sometimes|boolean',
            'RiskIndicator11' => 'sometimes|boolean',
        ]);

        $assessment->update($validated);
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

        $assessment = AssessmentAnswers::create($validated);

        return response()->json([
            'message' => 'Assessment saved',
            'data'    => $assessment
        ], 201);
    }

    // DELETE — delete
    public function destroy($uuid)
    {
        $assessment = AssessmentAnswers::find($uuid);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        $assessment->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }
}
