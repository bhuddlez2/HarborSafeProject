<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agency;

class AgencyController extends Controller
{
    // GET /api/agencies
    public function index()
    {
        return response()->json(
            Agency::all()
        );
    }

    // GET /api/agencies/{id}
    public function show($id)
    {
        $agency = Agency::find($id);

        if (!$agency) {
            return response()->json([
                'message' => 'Agency not found'
            ], 404);
        }

        return response()->json($agency);
    }

    // POST /api/agencies
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        return response()->json(
            Agency::create($validated), 201
        );
    }

    // PUT /api/agencies/{id}
    public function update(Request $request, $id)
    {
        $agency = Agency::find($id);

        if (!$agency) {
            return response()->json([
                'message' => 'Agency not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        $agency->update($validated);
        return response()->json($agency);
    }

    // DELETE /api/agencies/{id}
    public function destroy($id)
    {
        $agency = Agency::find($id);

        if (!$agency) {
            return response()->json([
                'message' => 'Agency not found'
            ], 404);
        }

        $agency->delete();
        return response()->json([
            'message' => 'Agency deleted successfully'
        ]);
    }
}
