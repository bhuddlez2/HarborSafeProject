<?php

use App\Enums\FollowUpKind;
use App\Enums\UserRole;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Services\FollowUps;
use App\Support\Timezones;
use Livewire\Livewire;

/*
The Follow-ups queue's search box finds the Name column: the victim on an
assessment, the requester on a resource request, by first, last or full name.
The names are encrypted and live on two databases, so each kind is matched
through its own form's visibleTo() (FollowUpResource::searchByName()).
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function searchQueue(string $search): \Livewire\Features\SupportTesting\Testable
{
    $today = now(Timezones::DISPLAY)->toDateString();

    return Livewire::test(ListFollowUps::class)
        ->filterTable('date_submitted', ['from' => $today, 'until' => $today])
        ->searchTable($search);
}

beforeEach(function () {
    $followUps = app(FollowUps::class);
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Search Follow-up PD'));

    $this->officerRow = $followUps->open(FollowUpKind::LawEnforcementAssessment, pestOfficerAssessment($officer, 'OfficerVictim')->DocumentID);
    $this->civilianRow = $followUps->open(FollowUpKind::CivilianAssessment, pestCivilianAssessment(overrides: [
        'VictimFirstName' => STAFF_TEST_PREFIX.'CivVictim',
        'VictimLastName' => 'Quillfeather',
    ])->DocumentID);
    $this->requestRow = pestFollowUp(FollowUpKind::ResourceRequest);
});

test('an admin finds each kind by first, last or full name', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    searchQueue(STAFF_TEST_PREFIX.'CivVictim quillfeather')
        ->assertCanSeeTableRecords([$this->civilianRow])
        ->assertCanNotSeeTableRecords([$this->officerRow, $this->requestRow]);

    searchQueue('OfficerVictim')
        ->assertCanSeeTableRecords([$this->officerRow])
        ->assertCanNotSeeTableRecords([$this->civilianRow, $this->requestRow]);

    searchQueue(publicFormsRunToken().' Requester')
        ->assertCanSeeTableRecords([$this->requestRow])
        ->assertCanNotSeeTableRecords([$this->officerRow, $this->civilianRow]);
});

test('a secretary finds resource requests only', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    searchQueue('Quillfeather')->assertCountTableRecords(0);
    searchQueue(publicFormsRunToken())->assertCanSeeTableRecords([$this->requestRow]);
});
