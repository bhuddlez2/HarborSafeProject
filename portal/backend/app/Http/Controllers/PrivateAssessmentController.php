<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrivateAssessment;


class PrivateAssessmentController extends Controller
{
        // GET /api/assessments
    public function index()
    {
        return response()->json(
            PrivateAssessment::all()
        );
    }

    // GET /api/assessments/{id}
    public function show($id)
    {
        $assessment = PrivateAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        return response()->json($assessment);
    }

    // POST /api/assessments
    public function store(Request $request)
    {
        $validated = $request->validate([
            'OffenderFirstName'             => 'required|string|max:50',
            'OffenderLastName'               => 'required|string|max:50',
            'OffenderSex'                   => 'required|string|max:10',
            'OffenderDOB'                   => 'nullable|date',
            'OffenderVictimRelationship'    => 'required|string|max:50',
            'VictimFirstName'               => 'required|string|max:50',
            'VictimLastName'                => 'required|string|max:50',
            'VictimSex'                     => 'required|string|max:10',
            'VictimDOB'                     => 'nullable|date',
            'VictimSafePhoneNumber'         => 'nullable|string|max:20',
            'SubmissionID'                  => 'nullable|uuid|exists:Portal._submitter_info,SubmissionID',
            'AssessmentDocID'               => 'required|uuid|exists:Portal._assessment_answers,AssessmentDocID',
        ]);

        return response()->json(
            PrivateAssessment::create($validated), 201
        );
    }

    // PUT /api/assessments/{id}
    public function update(Request $request, $id)
    {
        $assessment = PrivateAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        $validated = $request->validate([
            'OffenderFirstName'             => 'nullable|string|max:50',
            'OffenderLastName'               => 'nullable|string|max:50',
            'OffenderSex'                   => 'nullable|string|max:10',
            'OffenderDOB'                   => 'nullable|date',
            'OffenderVictimRelationship'    => 'nullable|string|max:50',
            'VictimFirstName'               => 'nullable|string|max:50',
            'VictimLastName'                => 'nullable|string|max:50',
            'VictimSex'                     => 'nullable|string|max:10',
            'VictimDOB'                     => 'nullable|date',
            'VictimSafePhoneNumber'         => 'nullable|string|max:20',
            'SubmissionID'                  => 'nullable|uuid|exists:Portal._submitter_info,SubmissionID',
            'AssessmentDocID'               => 'nullable|uuid|exists:Portal._assessment_answers,AssessmentDocID',
        ]);

        $assessment->update($validated);
        return response()->json($assessment);
    }

    // DELETE /api/assessments/{id}
    public function destroy($id)
    {
        $assessment = PrivateAssessment::find($id);

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        $assessment->delete();
        return response()->json([
            'message' => 'Assessment deleted successfully'
        ]);
    }
}
