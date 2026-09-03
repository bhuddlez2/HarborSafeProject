<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubmitterInfo;
use Illuminate\Http\Request;

class SubmitterInfoController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'SubmitterFirstName'    => 'required|string|max:50',
            'SubmitterLastName'     => 'required|string|max:50',
            'SubmitterEmail'        => 'nullable|email|max:100',
            'SubmitterPhoneNumber'  => 'nullable|string|max:20'
        ]);

        $submitter = SubmitterInfo::create($validated);

        return response()->json([
            'message' => 'Submitter info saved',
            'data'    => $submitter
        ], 201);
    }
}