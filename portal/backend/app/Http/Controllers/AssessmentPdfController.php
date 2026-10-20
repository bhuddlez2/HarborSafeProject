<?php

namespace App\Http\Controllers;

use App\Models\LawEnforcementAssessment;
use App\Support\RecordPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentPdfController extends Controller
{
    public function __invoke(Request $request, string $id): Response
    {
        // EnsureStaffPanelAccess (routes/web.php) has already turned away
        // anonymous and deactivated users.
        //
        // Looked up through visibleTo(), the same scope the assessment lists
        // and LawEnforcementAssessmentPolicy::view() use: admin everything,
        // police admin own agency only, officer own submissions only. Anyone
        // else's record is a 404, exactly as it is in the panel. Never fetch
        // the record unscoped here (Filament_CMS_Design.md section 5 rule 5).
        $record = LawEnforcementAssessment::visibleTo($request->user())
            ->with(['assessmentAnswers', 'submitter'])
            ->where('DocumentID', $id)
            ->firstOrFail();

        return RecordPdf::download(RecordPdf::ASSESSMENT, $record, 'LAP Assessment', 'assessment');
    }
}
