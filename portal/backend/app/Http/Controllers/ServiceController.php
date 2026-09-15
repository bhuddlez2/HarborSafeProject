<?php

namespace App\Http\Controllers;

use App\Models\Service;

class ServiceController extends Controller
{
    // GET /api/public/services
    public function index()
    {
        return response()->json(
            Service::on('FeedbackPublic')->select('id', 'Name')->get()
        );
    }
}
