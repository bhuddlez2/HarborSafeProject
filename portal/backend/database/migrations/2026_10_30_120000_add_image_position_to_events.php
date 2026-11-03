<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Where an event's image sits in the website's card: {x, y, zoom}, set by staff
dragging and zooming it on the Image step (Event::normalizeImagePosition()).
Null means centred with no zoom, which is how every existing event already shows.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->table('events', function (Blueprint $table) {
            $table->json('image_position')->nullable()->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->table('events', function (Blueprint $table) {
            $table->dropColumn('image_position');
        });
    }
};
