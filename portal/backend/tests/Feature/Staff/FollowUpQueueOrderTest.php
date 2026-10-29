<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Models\FollowUp;
use App\Support\Timezones;
use Livewire\Livewire;

/*
The Follow-ups queue's starting order per tab (Burchfield B42, 2026-10-28):
Needs Follow-Up oldest submitted first, so the longest wait is on top; every
other tab last updated, newest first, so the latest work is on top. The
tests' forms are all submitted today, and the queue is narrowed to today so
the local database's real forms don't push them off the page.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

function queueRow(FollowUpStatus $status, string $submittedAgo, ?string $updatedAgo): FollowUp
{
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $followUp->forceFill([
        'Status' => $status,
        'SubmittedAt' => now()->sub($submittedAgo),
        'LastUpdatedAt' => $updatedAgo === null ? null : now()->sub($updatedAgo),
    ])->save();

    return $followUp;
}

function queueTab(FollowUpStatus $status): \Livewire\Features\SupportTesting\Testable
{
    $today = now(Timezones::DISPLAY)->toDateString();

    return Livewire::test(ListFollowUps::class, ['activeTab' => $status->value])
        ->filterTable('date_submitted', ['from' => $today, 'until' => $today]);
}

test('Needs Follow-Up lists the longest wait first', function () {
    $newer = queueRow(FollowUpStatus::NeedsFollowUp, '10 minutes', null);
    $older = queueRow(FollowUpStatus::NeedsFollowUp, '20 minutes', null);
    $this->actingAs(staffUser(UserRole::Admin));

    queueTab(FollowUpStatus::NeedsFollowUp)->assertCanSeeTableRecords([$older, $newer], inOrder: true);
});

test('every other tab lists the latest update first, whatever was submitted first', function (FollowUpStatus $status) {
    // Submitted first, but updated longest ago.
    $earlier = queueRow($status, '30 minutes', '20 minutes');
    $latest = queueRow($status, '10 minutes', '5 minutes');
    $this->actingAs(staffUser(UserRole::Admin));

    queueTab($status)->assertCanSeeTableRecords([$latest, $earlier], inOrder: true);
})->with([
    FollowUpStatus::AttemptedContact,
    FollowUpStatus::Reached,
    FollowUpStatus::CouldNotReach,
    FollowUpStatus::Declined,
    FollowUpStatus::Closed,
]);
