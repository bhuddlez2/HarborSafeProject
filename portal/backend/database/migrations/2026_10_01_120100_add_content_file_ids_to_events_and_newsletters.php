<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Points events and newsletters at a row in `content_files` instead of a path on
a disk.

The existing `image_path` / `file_path` columns are deliberately LEFT IN PLACE
rather than dropped. Filament_CMS_Design.md section 4 keeps an S3-compatible
provider (R2/B2/Spaces) as the documented fallback if database storage turns
out not to suit the deploy target, and that retreat stays additive only while
both columns exist. So: exactly one of the pair is populated per record, and
the accessors on Event/Newsletter decide which to serve. Nothing writes
`*_path` today.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->table('events', function (Blueprint $table) {
            $table->uuid('image_file_id')->nullable()->after('image_path');
        });

        Schema::connection('Content')->table('newsletters', function (Blueprint $table) {
            $table->uuid('file_id')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->table('events', function (Blueprint $table) {
            $table->dropColumn('image_file_id');
        });

        Schema::connection('Content')->table('newsletters', function (Blueprint $table) {
            $table->dropColumn('file_id');
        });
    }
};
