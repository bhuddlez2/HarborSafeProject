<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
Every form already in the database starts in Needs follow-up (AS,
2026-10-20), keeping its real submission time. Reminders whose moment has
already passed (24 h, 48 h after submission) count as sent, so nothing fires
the first time the reminders run.

Written with the query builder rather than App\Services\FollowUps, so this
migration keeps working whatever that class becomes. Safe to run again: a form
that already has a follow-up is skipped.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        $portal = DB::connection('Portal');

        $this->open($portal->table('law_enforcement_assessment')->select(['DocumentID as id', 'DateCreated as at']), 'law_enforcement_assessment', 'LawEnforcementDocumentID');
        $this->open($portal->table('_private_assessment')->select(['DocumentID as id', 'DateCreated as at']), 'civilian_assessment', 'PrivateDocumentID');
        $this->open(DB::connection('Feedback')->table('resource_request_form')->select(['FormID as id', 'SubmissionDate as at']), 'resource_request', 'ResourceRequestFormID');
    }

    private function open($forms, string $kind, string $column): void
    {
        $portal = DB::connection('Portal');
        $existing = $portal->table('follow_ups')->whereNotNull($column)->pluck($column)->flip();

        foreach ($forms->get() as $form) {
            if (isset($existing[$form->id])) {
                continue;
            }

            $submittedAt = Carbon::parse($form->at ?? now(), 'UTC');
            $hours = $submittedAt->diffInHours(now(), true);

            $portal->table('follow_ups')->insert([
                'FollowUpID' => (string) Str::uuid(),
                'Kind' => $kind,
                $column => $form->id,
                'Status' => 'needs_follow_up',
                'SubmittedAt' => $submittedAt,
                'RemindersSent' => $hours >= 48 ? 2 : ($hours >= 24 ? 1 : 0),
            ]);
        }
    }

    public function down(): void
    {
        // The follow-up tables' own migration drops everything.
    }
};
