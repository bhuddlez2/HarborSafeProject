<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeedbackScan;
use App\Models\ServiceFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
Serves the scan of a paper service-feedback form (GET /feedback-scans/{file},
routes/web.php) - the upload preview in the "Add paper form" pop-up and the
link in an entry's View pop-up.

Deliberately narrower than ContentFileController::showStaff(), which serves
any content file to anyone who can open the panel:

  - Admins and secretaries only, the two roles that see service feedback.
    Everyone else gets a 404, indistinguishable from a missing file.
    EnsureStaffPanelAccess on the route has already turned away anonymous and
    deactivated users.
  - A saved scan must belong to a feedback entry; one whose entry has been
    deleted is gone with it (ServiceFeedback's deleted hook) and 404s anyway.
  - Never cached: `private, no-store` instead of the public, immutable
    caching event images get. A handwritten form from a member of the public
    should not linger in a browser or proxy cache.
*/
class FeedbackScanController extends Controller
{
    public function show(Request $request, string $file): Response
    {
        if (! $request->user()?->hasActiveRole(UserRole::Admin, UserRole::Secretary)) {
            throw new NotFoundHttpException;
        }

        $scan = FeedbackScan::find($file);
        $contents = $scan?->contents();

        if ($contents === null || ! ServiceFeedback::query()->where('ScanFileID', $scan->getKey())->exists()) {
            throw new NotFoundHttpException;
        }

        return response($contents, 200, [
            'Content-Type' => $scan->mime_type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="'.addslashes($scan->name).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'none'; sandbox",
        ]);
    }
}
