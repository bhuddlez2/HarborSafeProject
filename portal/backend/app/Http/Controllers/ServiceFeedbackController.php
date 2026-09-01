<?php

namespace App\Http\Controllers;

use App\Models\ServiceFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceFeedbackController extends Controller
{
    // POST /api/public/service-feedback
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ServiceID' => 'required|integer|exists:FeedbackPublic.services,id',
            'Rating'    => 'required|integer|between:1,5',
            'Comment'   => 'nullable|string|max:1000',
        ]);

        $feedback = ServiceFeedback::on('FeedbackPublic')->create($validated);

        DB::disconnect('FeedbackPublic');

        return response()->json([
            'message' => 'Feedback received',
            'FormID'  => $feedback->FormID,
        ], 201);
    }
}
