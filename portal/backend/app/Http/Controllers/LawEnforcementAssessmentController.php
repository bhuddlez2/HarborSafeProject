<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LawEnforcementAssessment;

class LawEnforcementAssessmentController extends Controller
{
    // GET /api/law-enforcement-assessments
    public function index()
    {
        return response()->json(
            LawEnforcementAssessment::all()
        );
    }

    // GET /api/law-enforcement-assessments/{DocumentID}
    public function show($id)
    {
        $assessment = LawEnforcementAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Law enforcement assessment not found'
            ], 404);
        }

        return response()->json($assessment);
    }

    // POST /api/law-enforcement-assessments
    public function store(Request $request)
    {
        $validated = $request->validate([
            'submitted_by' => 'required|integer|exists:Portal.users,id',
            'OffenderFirstName' => 'required|string|max:50',
            'OffenderLastName' => 'required|string|max:50',
            'OffenderSex' => 'required|string|max:10',
            'OffenderDOB' => 'nullable|date',
            'OffenderVictimRelationship' => 'nullable|string|max:50',
            'VictimFirstName' => 'required|string|max:50',
            'VictimLastName' => 'required|string|max:50',
            'VictimSex' => 'required|string|max:10',
            'VictimDOB' => 'nullable|date',
            'VictimSafePhoneNumber' => 'nullable|string|max:20',
            'AssessmentDocID' => 'required|uuid|exists:Portal._assessment_answers,AssessmentDocID',
        ]);

        return response()->json(
            LawEnforcementAssessment::create($validated), 201
        );
    }

    // PUT /api/law-enforcement-assessments/{DocumentID}
    public function update(Request $request, $id)
    {
        $assessment = LawEnforcementAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Law enforcement assessment not found'
            ], 404);
        }

        $validated = $request->validate([
            'submitted_by' => 'sometimes|required|integer|exists:Portal.users,id',
            'OffenderFirstName' => 'nullable|string|max:50',
            'OffenderLastName' => 'nullable|string|max:50',
            'OffenderSex' => 'nullable|string|max:10',
            'OffenderDOB' => 'nullable|date',
            'OffenderVictimRelationship' => 'nullable|string|max:50',
            'VictimFirstName' => 'nullable|string|max:50',
            'VictimLastName' => 'nullable|string|max:50',
            'VictimSex' => 'nullable|string|max:10',
            'VictimDOB' => 'nullable|date',
            'VictimSafePhoneNumber' => 'nullable|string|max:20',
            'AssessmentDocID' => 'nullable|uuid|exists:Portal._assessment_answers,AssessmentDocID',
        ]);

        $assessment->update($validated);
        return response()->json($assessment);
    }

    // DELETE /api/law-enforcement-assessments/{DocumentID}
    public function destroy($id)
    {
        $assessment = LawEnforcementAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Law enforcement assessment not found'
            ], 404);
        }

        $assessment->delete();
        return response()->json([
            'message' => 'Law enforcement assessment deleted successfully'
        ]);
    }
}
