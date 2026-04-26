<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssessmentAnswers;

class AssessmentController extends Controller
{
    public function index()
    {
        return view('portal.dashboard');
    }

    public function create()
    {
        return view('portal.assessment');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'RiskIndicator1'  => 'required|boolean',
            'RiskIndicator2'  => 'required|boolean',
            'RiskIndicator3'  => 'required|boolean',
            'RiskIndicator4'  => 'required|boolean',
            'RiskIndicator5'  => 'required|boolean',
            'RiskIndicator6'  => 'required|boolean',
            'RiskIndicator7'  => 'required|boolean',
            'RiskIndicator8'  => 'required|boolean',
            'RiskIndicator9'  => 'required|boolean',
            'RiskIndicator10' => 'required|boolean',
            'RiskIndicator11' => 'required|boolean',
        ]);

        AssessmentAnswers::create($validated);

        return redirect('/portal/dashboard')
            ->with('success', 'Assessment submitted successfully.');
    }
}
