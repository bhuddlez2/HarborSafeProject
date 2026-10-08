<?php

use App\Enums\UserRole;

/*
Who may download a law-enforcement assessment as a PDF
(AssessmentPdfController, /assessments/{id}/pdf).

The route sits outside the panel, so none of Filament's scoping runs on it -
the controller has to apply LawEnforcementAssessment::visibleTo() itself. It
first arrived letting any police admin download any agency's records, which
section 5 rule 5 of Filament_CMS_Design.md forbids. These pin that shut.

Only refusals are asserted. A successful download launches a real headless
Chromium through Browsershot, which is too slow and too machine-dependent for
this suite; the allowed cases are the same visibleTo() rows that
AssessmentFiltersTest and AssessmentChangeLogTest already cover.
*/

afterEach(fn () => cleanupStaffData());

test('a police admin cannot download another agency\'s assessment', function () {
    $world = pestTwoAgencies();

    $this->actingAs($world->policeAdminA)
        ->get(route('staff.assessments.pdf', $world->assessmentB->DocumentID))
        ->assertNotFound();
});

test('a police admin without an agency cannot download any assessment', function () {
    $world = pestTwoAgencies();

    $this->actingAs(pestAgencyMember(UserRole::PoliceAdmin, null))
        ->get(route('staff.assessments.pdf', $world->assessmentA->DocumentID))
        ->assertNotFound();
});

test('an officer cannot download another officer\'s assessment', function () {
    $world = pestTwoAgencies();

    $this->actingAs($world->officerA)
        ->get(route('staff.assessments.pdf', $world->assessmentB->DocumentID))
        ->assertNotFound();
});

test('a secretary cannot download an assessment', function () {
    // Section 5 rule 3: secretaries never see assessment PII.
    $world = pestTwoAgencies();

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(route('staff.assessments.pdf', $world->assessmentA->DocumentID))
        ->assertNotFound();
});

test('an anonymous visitor is sent to sign in', function () {
    $world = pestTwoAgencies();

    $this->get(route('staff.assessments.pdf', $world->assessmentA->DocumentID))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
});
