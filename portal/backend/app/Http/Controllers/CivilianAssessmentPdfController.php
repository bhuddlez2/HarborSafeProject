<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\PrivateAssessment;
use App\Support\RecordPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CivilianAssessmentPdfController extends Controller
{
    public function __invoke(Request $request, string $id): Response
    {
        // EnsureStaffPanelAccess (routes/web.php) has already turned away
        // anonymous and deactivated users. The route is outside the panel, so
        // CivilianAssessmentResource::canAccess() does not run: repeat it.
        // Active admins only - never secretary (section 5 rule 3), and never
        // police roles, whose row covers law-enforcement submissions alone.
        abort_unless($request->user()?->hasActiveRole(UserRole::Admin), 403);

        $record = PrivateAssessment::with(['assessmentAnswers', 'submitterInfo'])
            ->where('DocumentID', $id)
            ->firstOrFail();

        return RecordPdf::download(RecordPdf::ASSESSMENT, $record, 'LAP Assessment', 'civilian-assessment');
    }
}
