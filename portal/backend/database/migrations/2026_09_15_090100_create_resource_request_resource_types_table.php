<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Junction table for the resource-request form's multi-select "resources of
interest" field. One resource_request_form submission can name several
resource types, so this replaces the old single ResourceTypeID FK column
(dropped in the previous migration) with a proper many-to-many table.

No surrogate id/PK column - the pair is the identity, and there's no need to
reference a single row of this table from anywhere else.
*/
return new class extends Migration
{
    protected $connection = 'Feedback';

    public function up(): void
    {
        Schema::connection('Feedback')->create('resource_request_resource_types', function (Blueprint $table) {
            $table->uuid('FormID');
            $table->foreignId('ResourceTypeID')->constrained('resources');

            $table->primary(['FormID', 'ResourceTypeID']);
            $table->foreign('FormID')->references('FormID')->on('resource_request_form')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->dropIfExists('resource_request_resource_types');
    }
};
