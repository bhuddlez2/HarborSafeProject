<?php

namespace App\Http\Controllers;

use App\Models\County;

class CountyController extends Controller
{
    // GET /api/public/counties - in the order the website lists them, with
    // the built-in "Other" last (County).
    public function index()
    {
        return response()->json(
            County::on('FeedbackPublic')->select('id', 'Name')->otherLast()->orderBy('id')->get()
        );
    }
}
