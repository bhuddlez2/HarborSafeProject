<?php

use App\Enums\UserRole;
use App\Models\FeedbackScan;
use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Support\RecordPdf;

/*
The View pop-ups and PDF downloads on the Submissions screens (service
feedback, resource requests). Both render one partial each
(App\Support\RecordPdf), so these check the partials themselves, then who may
download.

Only refusals are asserted for the downloads here; every kind of record is
printed for real in PdfRenderingTest.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

function onlineFeedback(?string $comment = 'Kind and patient staff.'): ServiceFeedback
{
    $feedback = (new ServiceFeedback)->forceFill([
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 2,
        'Comment' => $comment,
        'SubmissionDate' => '2026-09-15 18:30:00',
        'Source' => ServiceFeedback::SOURCE_ONLINE,
    ]);
    $feedback->save();

    return $feedback->fresh('service');
}

function resourceRequest(array $attributes = []): ResourceRequestForm
{
    $request = (new ResourceRequestForm)->forceFill([
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'EmailAddress' => publicFormsEmail(),
        'SafePhoneNumber' => '423-555-0177',
        'CountyID' => publicFormsMakeCounty(),
        'Message' => "First line\nSecond line",
        'SubmissionDate' => '2026-09-15 18:30:00',
        ...$attributes,
    ]);
    $request->save();
    $request->resourceTypes()->attach(publicFormsMakeResource('Pest Shelter'));

    return $request->fresh(['county', 'resourceTypes']);
}

function renderRecord(string $partial, $record, bool $isPdf = false): string
{
    return view($partial, ['getRecord' => fn () => $record, 'isPdf' => $isPdf])->render();
}

test('an online feedback record shows its rating, service and comment in Eastern time', function () {
    $html = renderRecord(RecordPdf::SERVICE_FEEDBACK, onlineFeedback());

    expect($html)
        ->toContain('Service Feedback')
        ->toContain('Pest Test Service')
        ->toContain('2 out of 5')
        ->toContain('Submitted online')
        ->toContain('Kind and patient staff.')
        // 18:30 UTC is 2:30 pm Eastern in September (EDT).
        ->toContain('Sep 15, 2026 at 2:30 pm')
        ->not->toContain('Entered by');
});

test('feedback from the public is escaped, never rendered as markup', function () {
    $html = renderRecord(RecordPdf::SERVICE_FEEDBACK, onlineFeedback('<script>alert(1)</script>'));

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

test('a missing comment says so', function () {
    expect(renderRecord(RecordPdf::SERVICE_FEEDBACK, onlineFeedback(null)))->toContain('No comment left.');
});

test('a staff entry names who entered it and links its scan, but the PDF shows neither', function () {
    $scan = FeedbackScan::store('%PDF-1.4 test scan', staffRunToken().'-scan.pdf', 'application/pdf');
    $entry = ServiceFeedback::recordStaffEntry([
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 5,
        'SubmissionDate' => '2026-09-15 14:30',
        'ScanFileID' => $scan->getKey(),
    ], staffUser(UserRole::Secretary))->fresh('service');

    $scanUrl = route('staff.feedback-scans.show', ['file' => $scan->getKey()]);

    expect(renderRecord(RecordPdf::SERVICE_FEEDBACK, $entry))
        ->toContain('Entered by staff')
        ->toContain($entry->enteredByName())
        ->toContain($scanUrl);

    // The PDF is the form only: no source, staff name or scan (AS, 2026-10-20).
    expect(renderRecord(RecordPdf::SERVICE_FEEDBACK, $entry, isPdf: true))
        ->not->toContain($scanUrl)
        ->not->toContain('Entered by')
        ->not->toContain($entry->enteredByName())
        ->not->toContain('Scan of the form')
        ->not->toContain('Source');
});

test('a resource request shows contact details, county, resources and message', function () {
    $request = resourceRequest();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request))
        ->toContain('Resource Request')
        ->toContain(publicFormsRunToken().' Requester')
        ->toContain($request->EmailAddress)
        ->toContain('(423)555-0177')
        ->toContain('Pest Test County County')
        ->toContain('Pest Shelter')
        ->toContain("First line\nSecond line");
});

test('a resource request with nothing optional filled in still renders', function () {
    $request = resourceRequest([
        'LastName' => null,
        'SafePhoneNumber' => null,
        'CountyID' => null,
        'Message' => null,
    ]);
    $request->resourceTypes()->detach();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request->fresh(['county', 'resourceTypes'])))
        ->toContain('No county given')
        ->toContain('None selected.')
        ->toContain('No message left.');
});

/*
The email opens the computer's own email app (Finishing Touches #22), with the
address in To and nothing else: no subject, since subject lines show in inbox
previews someone else may see. The same partial is the View pop-up and the
follow-up page, so this covers both.
*/
test('on screen, the email opens an email to that address and nothing more', function () {
    $request = resourceRequest();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request))
        ->toContain('href="mailto:'.$request->EmailAddress.'"')
        ->not->toContain('subject=')
        ->not->toContain('?body=');
});

test('the PDF keeps the email as plain text', function () {
    $request = resourceRequest();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request, isPdf: true))
        ->toContain($request->EmailAddress)
        ->not->toContain('mailto:');
});

test('no link without an email, or for one that is not a valid address', function (?string $email, string $shown) {
    $request = resourceRequest(['EmailAddress' => $email]);

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request))
        ->toContain($shown)
        ->not->toContain('mailto:');
})->with([
    'phone only' => [null, '—'],
    'not an address' => ['not an address', 'not an address'],
    // Would add a second recipient if it were ever linked.
    'extra recipient smuggled in' => ['pest@example.com?cc=other@example.com', 'pest@example.com?cc=other@example.com'],
]);

/*
The safe phone is a call link on screen too (Burchfield B57), to the ten
digits only, and plain text in the PDF. Stored as (xxx)xxx-xxxx and shown that
way; anything that isn't a valid number is shown as it is, never linked.
*/
test('on screen, the safe phone is a call link to its ten digits', function () {
    $request = resourceRequest();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request))
        ->toContain('href="tel:+14235550177"')
        ->toContain('>(423)555-0177</a>');
});

test('the PDF keeps the safe phone as plain text', function () {
    $request = resourceRequest();

    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, $request, isPdf: true))
        ->toContain('(423)555-0177')
        ->not->toContain('tel:');
});

test('no call link without a safe phone, or for one that is not a valid number', function () {
    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, resourceRequest(['SafePhoneNumber' => null])))
        ->not->toContain('tel:');

    // Saved past validation somehow (the model formats valid numbers only).
    expect(renderRecord(RecordPdf::RESOURCE_REQUEST, resourceRequest(['SafePhoneNumber' => '555-0177'])))
        ->toContain('555-0177')
        ->not->toContain('tel:');
});

/*
The routes sit outside the panel, so the resources' canAccess() does not guard
them. Admins and secretaries only: SubmissionPdfController checks the role
(403); ResourceRequestPdfController looks the request up through
ResourceRequestForm::visibleTo(), so to anyone else it does not exist (404).
*/
test('police roles cannot download a submission PDF', function (UserRole $role, string $kind) {
    $record = $kind === 'feedback' ? onlineFeedback() : resourceRequest();
    $route = $kind === 'feedback' ? 'staff.service-feedback.pdf' : 'staff.resource-requests.pdf';

    $this->actingAs(staffUser($role))
        ->get(route($route, $record->getKey()))
        ->assertStatus($kind === 'feedback' ? 403 : 404);
})->with([
    'police_admin' => [UserRole::PoliceAdmin],
    'law_enforcement' => [UserRole::LawEnforcement],
])->with([
    'service feedback' => ['feedback'],
    'resource request' => ['request'],
]);

test('an anonymous visitor is sent to sign in for a submission PDF', function (string $kind) {
    $record = $kind === 'feedback' ? onlineFeedback() : resourceRequest();
    $route = $kind === 'feedback' ? 'staff.service-feedback.pdf' : 'staff.resource-requests.pdf';

    $this->get(route($route, $record->getKey()))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
})->with(['feedback', 'request']);

test('an unknown record is a 404 for an admin', function () {
    $this->actingAs(staffUser(UserRole::Admin))
        ->get(route('staff.resource-requests.pdf', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});
