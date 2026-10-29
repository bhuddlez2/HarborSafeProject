<?php

use App\Enums\UserRole;

/*
Who may download a law-enforcement assessment as a PDF
(AssessmentPdfController, /assessments/{id}/pdf).

The route sits outside the panel, so none of Filament's scoping runs on it -
the controller has to apply LawEnforcementAssessment::visibleTo() itself. It
first arrived letting any police admin download any agency's records, which
section 5 rule 5 of Filament_CMS_Design.md forbids. These pin that shut.

PDFs are printed in pure PHP (dompdf, App\Support\HtmlPdf) - no Node or
Chromium - so the allowed downloads are real here too: the submitting officer,
their agency's police admin and an admin each get the file. Everyone else gets
a 404, exactly as in the panel.
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

test('the submitting officer, their police admin and an admin get the PDF', function (string $who) {
    $world = pestTwoAgencies();
    $user = $who === 'admin' ? staffUser(UserRole::Admin) : $world->{$who};

    $response = $this->actingAs($user)
        ->get(route('staff.assessments.pdf', $world->assessmentA->DocumentID))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('assessment-'.substr($world->assessmentA->DocumentID, -8).'.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
})->with(['officerA', 'policeAdminA', 'admin']);

test('an anonymous visitor is sent to sign in', function () {
    $world = pestTwoAgencies();

    $this->get(route('staff.assessments.pdf', $world->assessmentA->DocumentID))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
});
