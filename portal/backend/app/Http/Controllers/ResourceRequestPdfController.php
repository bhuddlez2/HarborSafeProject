<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentAccess;
use App\Models\AssessmentAccessLog;
use App\Models\ResourceRequestForm;
use App\Services\AssessmentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// One resource request as a PDF (team plan task 16, part 5): the plain form,
// as if filled in on a printed sheet - no follow-up history, no notes, no
// "started by staff". Twin of the two assessment PDF controllers.
class ResourceRequestPdfController extends Controller
{
    public function __invoke(Request $request, string $id, AssessmentPdf $pdf): Response
    {
        // EnsureStaffPanelAccess (routes/web.php) has already turned away
        // anonymous and deactivated users. Outside the panel, so the record is
        // looked up through ResourceRequestForm::visibleTo() (admins and
        // secretaries); anyone else gets a 404.
        $record = ResourceRequestForm::visibleTo($request->user())
            ->with(['county', 'resourceTypes'])
            ->where('FormID', $id)
            ->firstOrFail();

        $bytes = $pdf->render($record);

        // It carries the requester's safe phone number: every download is
        // recorded, once the file exists.
        AssessmentAccessLog::record($record, AssessmentAccess::Download, $request->user());

        $filename = 'resource-request-' . substr($record->FormID, 0, 8) . '.pdf';

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
