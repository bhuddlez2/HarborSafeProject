<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\PrivateAssessment;
use App\Services\AssessmentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The civilian twin of AssessmentPdfController: one civilian assessment as a
// PDF, with everything the Civilian assessments View pop-up shows - including
// the submitter's details when they gave them.
class CivilianAssessmentPdfController extends Controller
{
    public function __invoke(Request $request, string $id, AssessmentPdf $pdf): Response
    {
        // EnsureStaffPanelAccess (routes/web.php) has already turned away
        // anonymous and deactivated users. This route is outside the panel,
        // so the Civilian assessments screen's own check doesn't run here:
        // PrivateAssessment::visibleTo() applies the same rule (admins only),
        // and anyone else gets a 404, as if the record didn't exist.
        $record = PrivateAssessment::visibleTo($request->user())
            ->with(['assessmentAnswers', 'submitterInfo'])
            ->where('DocumentID', $id)
            ->firstOrFail();

        $bytes = $pdf->render($record);

        // Recorded once the file exists, as for law-enforcement downloads.
        AssessmentAccessLog::record($record, AssessmentAccess::Download, $request->user());

        $filename = 'civilian-assessment-' . substr($record->DocumentID, 0, 8) . '.pdf';

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
