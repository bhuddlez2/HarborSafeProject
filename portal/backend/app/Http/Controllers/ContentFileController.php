<?php

namespace App\Http\Controllers;

use App\Models\ContentFile;
use App\Models\Event;
use App\Models\Newsletter;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
Serves an event image or newsletter PDF out of the database, standing in for
the URL a filesystem disk would otherwise have provided. See the
create_content_files_table migration for why the bytes live in a table.

There are two entry points because the two audiences need different rules:

  show()      - the public website. A file is readable only while some
                PUBLISHED event or newsletter points at it.
  showStaff() - the panel's upload preview and download. Any signed-in staff
                member who can reach the panel, published or not.

Why the public rule is phrased that way: content_files has no owner and no
published flag of its own, so the row cannot answer "may this be served". Its
reachability from published content can. The consequence is deliberate -
unpublishing an event takes its image offline, which is what the public API
already implies. Without it, a draft's image would stay readable forever to
anyone who had seen the URL, including after the draft was pulled.

Assessment PII never passes through here; nothing on the Portal connection
uses content_files.
*/
class ContentFileController extends Controller
{
    public function show(string $file): Response
    {
        if (! $this->isPubliclyReachable($file)) {
            // Deliberately indistinguishable from a missing file, so the
            // endpoint never confirms that a draft's image exists.
            throw new NotFoundHttpException;
        }

        return $this->stream($file);
    }

    // Route-protected: see routes/web.php, which puts this behind the panel's
    // auth middleware and a canAccessPanel() check.
    public function showStaff(string $file): Response
    {
        return $this->stream($file);
    }

    private function stream(string $file): Response
    {
        $record = ContentFile::find($file);

        if (! $record) {
            throw new NotFoundHttpException;
        }

        $contents = $record->contents();

        if ($contents === null) {
            throw new NotFoundHttpException;
        }

        return response($contents, 200, [
            'Content-Type' => $record->mime_type,
            'Content-Length' => (string) strlen($contents),
            // inline: images render in <img>, PDFs open in a viewer tab. The
            // filename is still offered when the viewer saves.
            'Content-Disposition' => 'inline; filename="'.addslashes($record->name).'"',
            // A file row's bytes are never edited in place - replacing an
            // upload creates a new uuid - so this really is immutable.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.($record->checksum ?: md5($contents)).'"',
            // These bytes arrived from an upload form. Even with a validated
            // mime type, stop the browser re-interpreting them, and stop
            // anything executing if an svg or html ever slips past.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'none'; sandbox",
        ]);
    }

    private function isPubliclyReachable(string $file): bool
    {
        return Event::query()
            ->where('is_published', true)
            ->where('image_file_id', $file)
            ->exists()
            || Newsletter::query()
                ->where('is_published', true)
                ->where('file_id', $file)
                ->exists();
    }
}
