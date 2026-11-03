<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\Police;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;

/*
The officer portal Home (App\Filament\Pages\Police), team plan task 11: police
admins get Assessment Review and Staff Management cards, under the Start
New Lethality Assessment card when they may open the wizard (an agency, team
plan task 7). Officers get a My Assessments card of their own. Each card is
named after the sidebar item it opens (2026-10-28, replacing tracker item
70's shared "My Assessments"); the police admin's names their agency. Every card under Start shares one light purple design (team
plan item 5). And the sidebar identity block: name, badge and agency on
separate rows, with the badge for officers only.
*/

afterEach(fn () => cleanupStaffData());

test('a police admin\'s Home offers Start New Lethality Assessment above Assessment Review and Staff Management', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Home PD')))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'Start New Lethality Assessment', 'Open a blank LAP screening form',
            'Assessment Review', "Home PD's submitted LAP screenings and change logs",
            // Test agency names start with a run token, hence the split.
            'Staff Management', 'Add and manage', "Home PD's staff accounts",
        ])
        ->assertDontSee('My Assessments')
        ->assertSee(NewAssessment::getUrl(), escape: false)
        ->assertSee(AssessmentReviewResource::getUrl('index'), escape: false)
        ->assertSee(OfficerAccountResource::getUrl('index'), escape: false)
        // My Assessments the screen stays officer-only; police admins review in Assessment review.
        ->assertDontSee(LawEnforcementAssessmentResource::getUrl('index'), escape: false);
});

test('a police admin with no agency gets no Start card', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, null))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertDontSee('Start New Lethality Assessment')
        ->assertDontSee(NewAssessment::getUrl(), escape: false);
});

test('an officer\'s Home offers Start New Lethality Assessment above My Assessments', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Home PD')))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Start New Lethality Assessment', 'My Assessments'])
        ->assertDontSee('View Assessments')
        ->assertSee(NewAssessment::getUrl(), escape: false)
        ->assertSee(LawEnforcementAssessmentResource::getUrl('index'), escape: false)
        ->assertSee('Each view is logged with your name and a timestamp.')
        ->assertDontSee('Staff Management');
});

test('the sidebar shows an officer\'s name, badge and agency', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Home PD'));
    $identity = $officer->officerIdentity();

    $this->actingAs($officer)
        ->get(Police::getUrl())
        ->assertSeeInOrder([$identity['name'], 'Badge '.$identity['badge'], $identity['agency']]);
});

test('the sidebar shows a police admin\'s name and agency but no badge', function () {
    $policeAdmin = pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Home PD'));
    $identity = $policeAdmin->officerIdentity();
    // Their row has a badge_number (the column is NOT NULL); it's never shown.
    $stored = $policeAdmin->lawEnforcementAgent->badge_number;

    expect($stored)->not->toBeNull();

    $this->actingAs($policeAdmin)
        ->get(Police::getUrl())
        ->assertSeeInOrder([$identity['name'], $identity['agency']])
        ->assertDontSee('Badge '.$stored)
        ->assertDontSee($stored);
});

test('the Staff Management page is headed like its sidebar item, with a New User button', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Home PD')))
        ->get(OfficerAccountResource::getUrl('index'))
        ->assertOk()
        ->assertSeeInOrder(['Staff Management', 'New User'])
        ->assertDontSee('New officer');
});
