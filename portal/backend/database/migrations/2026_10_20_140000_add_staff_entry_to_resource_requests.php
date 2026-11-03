<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Resource requests staff start themselves (team plan task 16, part 4): from a
final resource request ("New request from this one") or, admins only, from a
final assessment ("New resource request for this victim").

Source marks how a request arrived - online from the website (the default,
so the public endpoint's restricted INSERT needs no change) or staff - as
service_feedback already does. EnteredBy is the staff member (users lives on
Portal, so no foreign key), and CreatedFromFollowUpID the follow-up of the
form it was started from (follow_ups, also on Portal).

Set only by ResourceRequestForm::recordStaffEntry(); none of the three is
mass-assignable, so the website can't claim a staff author.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->string('Source', 10)->default('online')->after('Message');
            $table->unsignedBigInteger('EnteredBy')->nullable()->after('Source');
            $table->uuid('CreatedFromFollowUpID')->nullable()->after('EnteredBy');

            $table->index('CreatedFromFollowUpID');
        });
    }

    public function down(): void
    {
        Schema::connection('Feedback')->table('resource_request_form', function (Blueprint $table) {
            $table->dropIndex(['CreatedFromFollowUpID']);
            $table->dropColumn(['Source', 'EnteredBy', 'CreatedFromFollowUpID']);
        });
    }
};
