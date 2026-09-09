<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LawEnforcementAgent;

class LawEnforcementAgentController extends Controller
{
    // GET /api/law-enforcement-agents
    public function index()
    {
        return response()->json(
            LawEnforcementAgent::all()
        );
    }

    // GET /api/law-enforcement-agents/{user_id}
    public function show($userId)
    {
        $agent = LawEnforcementAgent::find($userId);

        if (!$agent) {
            return response()->json([
                'message' => 'Law enforcement agent not found'
            ], 404);
        }

        return response()->json($agent);
    }

    // POST /api/law-enforcement-agents
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:Portal.users,id|unique:Portal.law_enforcement_agents,user_id',
            'badge_number' => 'required|string|max:255',
            'agency_id' => 'nullable|integer|exists:Portal.agencies,id',
        ]);

        return response()->json(
            LawEnforcementAgent::create($validated), 201
        );
    }

    // PUT /api/law-enforcement-agents/{user_id}
    public function update(Request $request, $userId)
    {
        $agent = LawEnforcementAgent::find($userId);

        if (!$agent) {
            return response()->json([
                'message' => 'Law enforcement agent not found'
            ], 404);
        }

        $validated = $request->validate([
            'badge_number' => 'sometimes|required|string|max:255',
            'agency_id' => 'nullable|integer|exists:Portal.agencies,id',
        ]);

        $agent->update($validated);
        return response()->json($agent);
    }

    // DELETE /api/law-enforcement-agents/{user_id}
    public function destroy($userId)
    {
        $agent = LawEnforcementAgent::find($userId);

        if (!$agent) {
            return response()->json([
                'message' => 'Law enforcement agent not found'
            ], 404);
        }

        $agent->delete();
        return response()->json([
            'message' => 'Law enforcement agent deleted successfully'
        ]);
    }
}
