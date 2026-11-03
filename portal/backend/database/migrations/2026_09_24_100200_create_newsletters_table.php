<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Newsletter issues listed on the same Events & News page. Columns derived from
website/frontend/src/app/lib/mock-newsletters.json - see
Filament_CMS_Design.md section 6.

file_size_bytes and file_pages are nullable because the mock data has issues
with both unset, and EventsContent.js renders them conditionally
("PDF - 2.3 MB - 4 pages", with either half dropped when missing). They are
stored rather than derived so the listing doesn't have to stat every stored file
to render a list.

file_path is a path on the configured filesystem disk, not a URL; the API
resource turns it into the site's file.url. Which disk matters: everything on
the public disk is reachable by whoever guesses the filename, so an issue that
must not be openly readable belongs on the private disk behind a route that
checks authorization. See Filament_CMS_Design.md section 6.6 - that per-issue
decision is still open.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->create('newsletters', function (Blueprint $table) {
            $table->uuid('NewsletterID')->primary();

            $table->string('title', 200);
            $table->date('issue_date');
            $table->string('summary', 500)->nullable();

            $table->string('file_path', 255)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedSmallInteger('file_pages')->nullable();

            $table->boolean('is_published')->default(false);

            // The public endpoint filters on is_published and orders by
            // issue_date descending; this covers both.
            $table->index(['is_published', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->dropIfExists('newsletters');
    }
};
