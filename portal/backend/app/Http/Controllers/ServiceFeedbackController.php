<?php

namespace App\Http\Controllers;

use App\Enums\NewSubmission;
use App\Http\Requests\Public\ServiceFeedbackStoreRequest;
use App\Models\ServiceFeedback;
use App\Services\SubmissionAlerts;
use Illuminate\Support\Facades\DB;

class ServiceFeedbackController extends Controller
{
    // POST /api/public/service-feedback
    public function store(ServiceFeedbackStoreRequest $request, SubmissionAlerts $alerts)
    {
        $feedback = ServiceFeedback::on('FeedbackPublic')->create($request->validated());

        DB::disconnect('FeedbackPublic');

        // Website feedback only: feedback staff enter in the panel
        // (ServiceFeedback::recordStaffEntry()) doesn't come through here.
        $alerts->raise(NewSubmission::ServiceFeedback);

        return response()->json([
            'message' => 'Feedback received',
            'FormID'  => $feedback->FormID,
        ], 201);
    }
}
