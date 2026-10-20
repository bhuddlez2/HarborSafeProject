<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Pages\Police;
use App\Filament\Resources\AssessmentReview\AssessmentReviewResource;
use App\Filament\Resources\LawEnforcementAssessments\LawEnforcementAssessmentResource;
use App\Filament\Resources\OfficerAccounts\OfficerAccountResource;

/*
The officer portal Home (App\Filament\Pages\Police), team plan task 11: police
admins get View Assessments and Personnel Management cards; the officer's Home
is unchanged. And the sidebar identity block: name, badge and agency on
separate rows, with the badge for officers only.
*/

afterEach(fn () => cleanupStaffData());

test('a police admin\'s Home offers View Assessments and Personnel Management', function () {
    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Home PD')))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertSee('View Assessments')
        ->assertSee(AssessmentReviewResource::getUrl('index'), escape: false)
        ->assertSee('Personnel Management')
        ->assertSee(OfficerAccountResource::getUrl('index'), escape: false)
        ->assertDontSee('Start New Lethality Assessment')
        ->assertDontSee('Go to My Assessments');
});

test('an officer\'s Home is unchanged', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Home PD')))
        ->get(Police::getUrl())
        ->assertOk()
        ->assertSee('Start New Lethality Assessment')
        ->assertSee(NewAssessment::getUrl(), escape: false)
        ->assertSee('Go to My Assessments')
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

    expect($identity['badge'])->not->toBeNull();

    $this->actingAs($policeAdmin)
        ->get(Police::getUrl())
        ->assertSeeInOrder([$identity['name'], $identity['agency']])
        ->assertDontSee('Badge '.$identity['badge']);
});
