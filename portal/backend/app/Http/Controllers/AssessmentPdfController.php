<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use App\Services\AssessmentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentPdfController extends Controller
{
    public function __invoke(Request $request, string $id, AssessmentPdf $pdf): Response
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

        // Rendered by App\Services\AssessmentPdf (dompdf, pure PHP), shared with
        // the civilian download.
        $bytes = $pdf->render($record);

        // The PDF carries the victim's safe phone number, so every download
        // is recorded: who and when. Only once the file exists - a failed
        // render downloads nothing.
        AssessmentAccessLog::record($record, AssessmentAccess::Download, $request->user());

        // Prepare the filename for the PDF download.
        $filename = 'assessment-' . substr($record->DocumentID, -8) . '.pdf';

        // Return the generated PDF as a response with appropriate headers for download.
        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
