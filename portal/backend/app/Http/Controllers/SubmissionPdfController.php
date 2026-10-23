<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ServiceFeedback;
use App\Services\AssessmentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
One service feedback form as a PDF, from the Submissions screen's View pop-up.

The route is outside the panel, so ServiceFeedbackResource::canAccess() does
not run on it: this repeats it - active admins and secretaries only. The PDF
is the form itself (date, service, rating, comment): who entered it and the
scan stay off it (App\Support\RecordPdf, isPdf). Not logged: feedback carries
no safe phone number and is data collection (AS, 2026-10-20).

Resource request PDFs are ResourceRequestPdfController's, which records every
download.
*/
class SubmissionPdfController extends Controller
{
    public function serviceFeedback(Request $request, string $id, AssessmentPdf $pdf): Response
    {
        abort_unless($request->user()?->hasActiveRole(UserRole::Admin, UserRole::SuperAdmin, UserRole::Secretary), 403);

        $record = ServiceFeedback::with('service')->whereKey($id)->firstOrFail();

        return response($pdf->render($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="service-feedback-'.substr((string) $record->getKey(), 0, 8).'.pdf"',
        ]);
    }
}
