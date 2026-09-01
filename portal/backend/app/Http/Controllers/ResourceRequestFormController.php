<?php

namespace App\Http\Controllers;

use App\Models\ResourceRequestForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceRequestFormController extends Controller
{
    // POST /api/public/resource-requests
    public function store(Request $request)
    {
        $validated = $request->validate([
            'FirstName'      => 'required|string|max:50',
            'LastName'       => 'nullable|string|max:50',
            'EmailAddress'   => 'required|email|max:250',
            'SafePhoneNumber' => 'required|string|max:20',
            'ResourceTypeID' => 'nullable|integer|exists:FeedbackPublic.resources,id',
            'CountyID'       => 'nullable|integer|exists:FeedbackPublic.counties,id',
            'Message'        => 'nullable|string|max:1000',
        ]);

        $resourceRequest = ResourceRequestForm::on('FeedbackPublic')->create($validated);

        DB::disconnect('FeedbackPublic');

        return response()->json([
            'message' => 'Request received',
            'FormID'  => $resourceRequest->FormID,
        ], 201);
    }
}
