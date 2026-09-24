<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\ResourceRequestStoreRequest;
use App\Models\ResourceRequestForm;
use Illuminate\Support\Facades\DB;

class ResourceRequestFormController extends Controller
{
    // POST /api/public/resource-requests
    public function store(ResourceRequestStoreRequest $request)
    {
        $validated = $request->validated();
        $resourceTypeIds = $validated['ResourceTypeIDs'] ?? [];
        unset($validated['ResourceTypeIDs']);

        $resourceRequest = DB::connection('FeedbackPublic')->transaction(function () use ($validated, $resourceTypeIds) {
            $resourceRequest = ResourceRequestForm::on('FeedbackPublic')->create($validated);

            if (! empty($resourceTypeIds)) {
                $junctionRows = array_map(
                    fn ($resourceTypeId) => [
                        'FormID'         => $resourceRequest->FormID,
                        'ResourceTypeID' => $resourceTypeId,
                    ],
                    $resourceTypeIds
                );

                DB::connection('FeedbackPublic')
                    ->table('resource_request_resource_types')
                    ->insert($junctionRows);
            }

            return $resourceRequest;
        });

        DB::disconnect('FeedbackPublic');

        return response()->json([
            'message' => 'Request received',
            'FormID'  => $resourceRequest->FormID,
        ], 201);
    }
}
