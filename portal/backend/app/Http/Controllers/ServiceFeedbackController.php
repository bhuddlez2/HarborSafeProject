<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\ServiceFeedbackStoreRequest;
use App\Models\ServiceFeedback;
use Illuminate\Support\Facades\DB;

class ServiceFeedbackController extends Controller
{
    // POST /api/public/service-feedback
    public function store(ServiceFeedbackStoreRequest $request)
    {
        $feedback = ServiceFeedback::on('FeedbackPublic')->create($request->validated());

        DB::disconnect('FeedbackPublic');

        return response()->json([
            'message' => 'Feedback received',
            'FormID'  => $feedback->FormID,
        ], 201);
    }
}
