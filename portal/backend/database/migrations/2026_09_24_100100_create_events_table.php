<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Events shown on the public site's Events & News page. The column list is
derived from website/frontend/src/app/lib/mock-events.json, which this table
replaces - see Filament_CMS_Design.md section 6.

Nested objects in that JSON (location, image, registration) are flattened into
columns here and rebuilt by the API resource on the way out. Two things that
serialization has to get right, both driven by how EventsContent.js reads them:

  - When every column behind a nested object is null, emit null for the whole
    object rather than an object full of nulls. The page guards with plain
    truthiness (`event.location &&`, `event.registration &&`), so
    {name: null, address: null} renders an empty row instead of nothing.
  - description is an array of paragraph strings, not HTML. EventsContent.js
    does description.map((paragraph) => ...), so a rich-text editor's HTML
    output would render as visible escaped markup. Use a repeater of textareas.

starts_at/ends_at are stored in UTC. The site formats them in
America/New_York (TIME_ZONE in website/frontend/src/app/lib/content.js) and the
mock data carries real offsets that shift with DST (-05:00 in January, -04:00
in March), so the panel must convert on the way in rather than writing a naive
wall-clock value straight into these columns.

is_published and is_cancelled are independent flags, not a single status: a
published event can also be cancelled, which the page renders as a "cancelled"
badge with the registration link suppressed.
*/
return new class extends Migration
{
    protected $connection = 'Content';

    public function up(): void
    {
        Schema::connection('Content')->create('events', function (Blueprint $table) {
            $table->uuid('EventID')->primary();

            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->json('description')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            // Display text only ("Annually in September") - this does not
            // generate occurrences, and nothing expands it into real dates.
            $table->string('recurrence', 120)->nullable();

            $table->string('location_name', 150)->nullable();
            $table->string('location_address', 255)->nullable();
            $table->boolean('location_is_virtual')->default(false);
            $table->string('location_virtual_note', 255)->nullable();

            // Object-storage key, not a URL. The API resource turns it into an
            // absolute URL for the site's image.src.
            $table->string('image_path', 255)->nullable();
            $table->string('image_alt', 255)->nullable();

            $table->string('registration_url', 255)->nullable();
            $table->string('registration_label', 100)->nullable();

            $table->foreignId('category_id')->nullable()->constrained('event_categories');

            $table->boolean('is_published')->default(false);
            $table->boolean('is_cancelled')->default(false);

            // The public endpoint filters on is_published and orders by
            // starts_at; this covers both.
            $table->index(['is_published', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('Content')->dropIfExists('events');
    }
};
