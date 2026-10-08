<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\LawEnforcementAssessment;
use Filament\Facades\Filament;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

class AssessmentPdfController extends Controller
{
    public function __invoke(string $id): Response
    {
        // Retrieve the assessment record by its DocumentID, including related assessment answers and the submitter.
        $record = LawEnforcementAssessment::with(['assessmentAnswers', 'submitter'])
            ->where('DocumentID', $id)
            ->firstOrFail();

        // Ensure the user is authenticated before proceeding.
        $user = Filament::auth()->user();
        // Abort with a 403 Forbidden response if the user is not authenticated.
        abort_if(! $user, 403);

        // Determine if the authenticated user has permission to view the assessment record.
        $canView = $user->hasActiveRole(UserRole::PoliceAdmin, UserRole::Admin)
            || ($user->hasActiveRole(UserRole::LawEnforcement)
                && (int) $record->submitted_by === $user->id);
        // Abort with a 403 Forbidden response if the user does not have permission to view the record. (null catch)
        abort_if(! $canView, 403);

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
