<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Services\AssessmentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
"Download PDF with log" on a form's follow-up page (Burchfield B43,
2026-10-28): the form plus its follow-up Log, for when Harbor Safe has to
provide the record with its history. The plain "Download PDF" stays the
form only; this is the one export that carries follow-up notes.

The follow-up is looked up through FollowUp::visibleTo(), so only those who
work that kind of form get it (admins: assessments; admins and Staff:
resource requests); officers, police admins and anyone else get a 404. The
form carries a safe phone number and the notes can hold names, so every
download is recorded, as its own kind (AssessmentAccess::DownloadWithLog),
once the file exists.
*/
class FollowUpPdfController extends Controller
{
    public function __invoke(Request $request, string $id, AssessmentPdf $pdf): Response
    {
        $followUp = FollowUp::visibleTo($request->user())->whereKey($id)->firstOrFail();
        $form = $followUp->form() ?? abort(404);

        $bytes = $pdf->renderWithLog($followUp);

        AssessmentAccessLog::record($form, AssessmentAccess::DownloadWithLog, $request->user());

        $name = match (true) {
            $form instanceof LawEnforcementAssessment => 'assessment-'.substr($form->DocumentID, -8),
            $form instanceof PrivateAssessment => 'civilian-assessment-'.substr($form->DocumentID, -8),
            default => 'resource-request-'.substr($form->FormID, -8),
        };

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'-with-log.pdf"',
        ]);
    }
}
