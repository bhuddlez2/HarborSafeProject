<?php

namespace App\Http\Controllers;

use App\Models\Resource;

class ResourceController extends Controller
{
    // GET /api/public/resources
    public function index()
    {
        return response()->json(
            Resource::on('FeedbackPublic')->get()
        );
    }
}
