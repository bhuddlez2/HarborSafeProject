<?php

namespace App\Http\Controllers;

use App\Enums\OffenderRelationship;
use Illuminate\Http\JsonResponse;

/*
GET /api/public/relationships - the "Relationship to victim" options for the
civilian assessment, so the form never keeps its own copy of the list.

Served straight from App\Enums\OffenderRelationship: no database, so no
connection or restricted user is involved. A bare array in list order, the
same shape as /api/public/services: [{ "value": "spouse", "label": "Spouse" }].
The form sends back `value`.
*/
class RelationshipController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            collect(OffenderRelationship::cases())
                ->map(fn (OffenderRelationship $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->all()
        );
    }
}
