<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Models\FollowUp;
use App\Services\FollowUps;
use Livewire\Livewire;

/*
Overdue rows on the Follow-ups queue (Burchfield B41, 2026-10-28;
FollowUp::overdueHours()). A form still in Needs Follow-Up 24 hours after
submission is tinted yellow, 48 hours red, with an "Over 24 hours" / "Over
48 hours" tag under its date: the same clock as the bell reminders. Other
statuses are never coloured.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

function overdueAfter(float $hours, FollowUpStatus $status = FollowUpStatus::NeedsFollowUp): FollowUp
{
    return (new FollowUp)->forceFill(['Status' => $status, 'SubmittedAt' => now()->subMinutes((int) round($hours * 60))]);
}

test('the thresholds are the reminder hours', function () {
    expect(FollowUps::REMINDER_HOURS)->toBe([24, 48])
        ->and(overdueAfter(23.9)->overdueHours())->toBeNull()
        ->and(overdueAfter(24)->overdueHours())->toBe(24)
        ->and(overdueAfter(47.9)->overdueHours())->toBe(24)
        ->and(overdueAfter(48)->overdueHours())->toBe(48)
        ->and(overdueAfter(400)->overdueHours())->toBe(48);
});

test('only Needs Follow-Up is ever overdue', function (FollowUpStatus $status) {
    expect(overdueAfter(72, $status)->overdueHours())->toBeNull();
})->with(collect(FollowUpStatus::cases())->reject(fn ($s) => $s === FollowUpStatus::NeedsFollowUp)->all());

test('the queue tints overdue rows and tags them under the date', function () {
    $fresh = pestFollowUp(FollowUpKind::ResourceRequest);
    $day = pestFollowUp(FollowUpKind::ResourceRequest);
    $twoDays = pestFollowUp(FollowUpKind::ResourceRequest);
    $day->forceFill(['SubmittedAt' => now()->subHours(25)])->save();
    $twoDays->forceFill(['SubmittedAt' => now()->subHours(49)])->save();

    $this->actingAs(staffUser(UserRole::Admin));
    $table = Livewire::test(ListFollowUps::class)->instance()->getTable();
    $submitted = $table->getColumn('SubmittedAt');

    expect($table->getRecordClasses($fresh->refresh()))->toContain('hs-card')->not->toContain('hs-overdue-24', 'hs-overdue-48')
        ->and($table->getRecordClasses($day->refresh()))->toContain('hs-card', 'hs-overdue-24')
        ->and($table->getRecordClasses($twoDays->refresh()))->toContain('hs-card', 'hs-overdue-48')
        ->and($submitted->record($fresh)->getDescriptionBelow())->toBeNull()
        ->and((string) $submitted->record($day)->getDescriptionBelow())->toContain('Over 24 hours')
        ->and((string) $submitted->record($twoDays)->getDescriptionBelow())->toContain('Over 48 hours');
});

test('starting work on an overdue form takes the colour away', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $followUp->forceFill(['SubmittedAt' => now()->subHours(49)])->save();
    $admin = staffUser(UserRole::Admin);

    app(FollowUps::class)->update($followUp, FollowUpStatus::AttemptedContact, 'Left a message.', $admin);

    $this->actingAs($admin);
    $table = Livewire::test(ListFollowUps::class)->instance()->getTable();

    expect($table->getRecordClasses($followUp->refresh()))->not->toContain('hs-overdue-48');
});
