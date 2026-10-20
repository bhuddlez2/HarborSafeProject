<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Support\RecordPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
PDF downloads from the Submissions screens' View pop-ups.

These routes are outside the panel, so the resources' canAccess() does not run
on them: each action repeats it. Active admins and secretaries only, as on
ServiceFeedbackResource and ResourceRequestResource. A resource request carries
a safe phone number, so a PDF of one inherits that screen's caution.
*/
class SubmissionPdfController extends Controller
{
    public function serviceFeedback(Request $request, string $id): Response
    {
        $this->authorizeSubmissions($request);

        $record = ServiceFeedback::with('service')->whereKey($id)->firstOrFail();

        return RecordPdf::download(RecordPdf::SERVICE_FEEDBACK, $record, 'Service Feedback', 'service-feedback');
    }

    public function resourceRequest(Request $request, string $id): Response
    {
        $this->authorizeSubmissions($request);

        $record = ResourceRequestForm::with(['county', 'resourceTypes'])->whereKey($id)->firstOrFail();

        return RecordPdf::download(RecordPdf::RESOURCE_REQUEST, $record, 'Resource Request', 'resource-request');
    }

    private function authorizeSubmissions(Request $request): void
    {
        abort_unless($request->user()?->hasActiveRole(UserRole::Admin, UserRole::Secretary), 403);
    }
}
