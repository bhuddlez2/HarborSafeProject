<?php

namespace App\Http\Controllers;

use App\Models\County;

class CountyController extends Controller
{
    // GET /api/public/counties
    public function index()
    {
        return response()->json(
            County::on('FeedbackPublic')->get()
        );
    }
}
