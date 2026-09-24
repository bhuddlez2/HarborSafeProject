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
stored rather than derived so the listing doesn't have to stat every object in
the bucket to render a list.

file_path is an object-storage key, not a URL; the API resource turns it into
the site's file.url. Whether that is a plain public URL or an expiring signed
one depends on the public-vs-private decision in Filament_CMS_Design.md
section 6.6, which is still open - newsletters may not all be intended to be
world-readable by URL.
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
