<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Lookup table for the events page's category badge. Same shape as the
services/resources/counties lookups on the Feedback connection - auto-increment
id, a display Name, and a nullable ChangeDate - so the staff panel manages all
four the same way.

Note for the API layer: the website renders the category as a plain string
(see CategoryBadge in website/frontend/src/app/events/EventsContent.js), so the
public endpoint must serialize this table's Name, never category_id.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('Name', 250);
            $table->timestamp('ChangeDate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->dropIfExists('event_categories');
    }
};
