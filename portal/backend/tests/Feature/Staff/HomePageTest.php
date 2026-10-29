<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\Police;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;

/*
The officer portal Home (App\Filament\Pages\Police), team plan task 11: police
admins get My Assessments and Personnel Management cards, under the Start
New Lethality Assessment card when they may open the wizard (an agency, team
plan task 7). Officers get a My Assessments card of their own. Both cards are
called My Assessments (tracker item 70); a police admin's opens
Assessment review, an officer's opens My Assessments, and the sidebar keeps
its own names. Every card under Start shares one light purple design (team
plan item 5). And the sidebar identity block: name, badge and agency on
separate rows, with the badge for officers only.
*/

afterEach(fn () => cleanupStaffData());

test('a police admin\'s Home offers Start New Lethality Assessment above My Assessments and Personnel Management', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Home PD')))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Start New Lethality Assessment', 'My Assessments', 'Personnel Management'])
        ->assertDontSee('View Assessments')
        ->assertSee(NewAssessment::getUrl(), escape: false)
        // The card is called My Assessments but still opens Assessment review,
        // which keeps its name in the sidebar.
        ->assertSee(AssessmentReviewResource::getUrl('index'), escape: false)
        ->assertSee('Assessment Review')
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
        ->assertDontSee('Personnel Management');
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
