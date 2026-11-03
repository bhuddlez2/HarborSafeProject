<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Event images and newsletter PDFs, stored as rows rather than as files on a disk.

Why the database and not the filesystem: the deploy target is a single Ionos
server, and Filament_CMS_Design.md section 3.2 lists `php artisan storage:link`
there as untested and unresolved. Keeping bytes in the database removes that
dependency, needs no cloud account (ruled out in section 4), and means a
database dump is a complete backup - there is no second artifact to remember.
The tradeoffs are real but small at this volume: a few dozen images and a
quarterly PDF.

TWO CONSTRAINTS THAT WILL BITE IF IGNORED:

  1. `max_allowed_packet` caps a single row's transfer, server-side. It is
     16 MB on both the dev box and a default MariaDB install, and a blob
     larger than that fails on INSERT *and* on SELECT, with an error that does
     not obviously name the cause. App\Filament\Forms\Components\DatabaseFileUpload
     caps uploads well under it. Raising the cap means editing my.ini on the
     server, so do not design around a larger limit.

  2. `contents` must never be loaded by a listing query. A LONGBLOB in a
     SELECT * pulls every byte into PHP memory, so a 20-row table page would
     move hundreds of megabytes. App\Models\ContentFile adds a global scope
     that selects every column except this one; the streaming controller is
     the only thing that reads it, one row at a time.

The bytes live here rather than in a column on `events`/`newsletters` for the
same reason - those tables are read constantly by the public API, and a blob
on them could not be excluded as cleanly.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->create('content_files', function (Blueprint $table) {
            $table->uuid('FileID')->primary();

            // The name as uploaded, used for the download filename and shown
            // in the panel. Not unique and not a path - nothing resolves a
            // file by name.
            $table->string('name', 255);

            // Served back verbatim as the Content-Type, so it is validated on
            // upload against an allow-list rather than trusted.
            $table->string('mime_type', 127);

            // Denormalised from the blob so listings and the public API can
            // show a size without reading `contents`.
            $table->unsignedInteger('size_bytes');

            // LONGBLOB. Laravel's binary() maps to BLOB (65 KB) on MySQL and
            // MariaDB, which is far too small for a PDF, so the column type is
            // stated outright.
            $table->longText('contents')->charset('binary');

            // Lets a re-upload of identical bytes be spotted, and gives
            // integrity checking something to compare against.
            $table->char('checksum', 64)->nullable();

            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->dropIfExists('content_files');
    }
};
