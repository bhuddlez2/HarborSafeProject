<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Brings resource_request_form in line with the merged frontend and the design
review in Public_Forms_Backend_Design.md:

- FirstName/LastName shrink from varchar(100) to varchar(50) to match what
  validation and the form input actually allow end-to-end (strict mode means
  a mismatch here would surface as a DB error instead of a clean 422).
- EmailAddress/SafePhoneNumber become nullable - the form only requires one
  of the two (a way to contact the submitter), not both.
- ResourceTypeID (singular FK) is dropped. The form is multi-select, so the
  chosen resource types now live in the resource_request_resource_types
  junction table (see the following migration) instead of a single column.
*/
return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->dropForeign(['ResourceTypeID']);
            $table->dropColumn('ResourceTypeID');
        });

        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->string('FirstName', 50)->change();
            $table->string('LastName', 50)->nullable()->change();
            $table->string('EmailAddress', 250)->nullable()->change();
            $table->string('SafePhoneNumber', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->string('FirstName', 100)->change();
            $table->string('LastName', 100)->nullable()->change();
            $table->string('EmailAddress', 250)->nullable(false)->change();
            $table->string('SafePhoneNumber', 20)->nullable(false)->change();
        });

        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->foreignId('ResourceTypeID')->nullable()->constrained('resources');
        });
    }
};
