<?php

namespace App\Http\Controllers;

use App\Models\LawEnforcementAssessment;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
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

        // Render the assessment record as an HTML view for PDF generation.
        $html = view('filament.pdf.assessment', [
            'getRecord' => fn () => $record,
        ])->render();

        // Generate the PDF from the rendered HTML using Browsershot.
        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->noSandbox()
            ->showBackground()
            ->timeout(30)
            ->addChromiumArguments([
                '--disable-background-networking',
                '--disable-extensions',
                '--disable-sync',
            ])
            ->pdf();

        // Prepare the filename for the PDF download.
        $filename = 'assessment-' . substr($record->DocumentID, 0, 8) . '.pdf';

        // Return the generated PDF as a response with appropriate headers for download.
        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
