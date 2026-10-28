<?php

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\FollowUps\Pages\ViewFollowUp;
use App\Models\FollowUp;
use App\Models\ResourceRequestForm;
use App\Notifications\NewSubmissionAlert;
use App\Services\FollowUps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
Follow-up tracking, part 4 (team plan task 16): starting a new resource request
from a form whose follow-up is final - "New request from this one" on a
resource request, "New resource request for this victim" (admins only) on an
assessment. Never a new assessment. The new request is marked Staff, raises no
alert, and opens its own follow-up.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function finishedFollowUp(FollowUpKind $kind, $by, FollowUpStatus $status = FollowUpStatus::Closed): FollowUp
{
    $followUp = pestFollowUp($kind);
    app(FollowUps::class)->update($followUp, $status, 'Done for now.', $by);

    return $followUp->refresh();
}

test('a final resource request starts a new one, filled in from it and editable', function () {
    $secretary = staffUser(UserRole::Secretary);
    $from = finishedFollowUp(FollowUpKind::ResourceRequest, $secretary, FollowUpStatus::CouldNotReach);
    $original = $from->resourceRequest;

    $this->actingAs($secretary);
    $page = Livewire::test(ViewFollowUp::class, ['record' => $from->getKey()])
        ->assertActionVisible('new_request')
        ->assertActionHasLabel('new_request', 'New request from this one');

    expect($page->instance()->newRequestDefaults())->toMatchArray([
        'FirstName' => $original->FirstName,
        'LastName' => 'Requester',
        'EmailAddress' => $original->EmailAddress,
        'SafePhoneNumber' => '(423)555-0177',
    ]);

    // A fresh record of alerts: the original website request raised its own.
    Notification::fake();

    // The new safe number replaces the old one in the new request.
    $page->callAction('new_request', data: [
        ...$page->instance()->newRequestDefaults(),
        'SafePhoneNumber' => '423-555-0188',
    ])->assertHasNoActionErrors();

    $started = ResourceRequestForm::where('CreatedFromFollowUpID', $from->getKey())->sole();
    $followUp = FollowUp::where('ResourceRequestFormID', $started->FormID)->sole();

    $page->assertRedirect($followUp->url());

    expect($started->Source)->toBe(ResourceRequestForm::SOURCE_STAFF)
        ->and($started->EnteredBy)->toBe($secretary->getKey())
        ->and($started->FirstName)->toBe($original->FirstName)
        ->and($started->EmailAddress)->toBe($original->EmailAddress)
        ->and($started->SafePhoneNumber)->toBe('(423)555-0188')
        ->and($followUp->Status)->toBe(FollowUpStatus::NeedsFollowUp)
        ->and($followUp->RemindersSent)->toBe(0)
        // The original is left as it was.
        ->and($original->refresh()->SafePhoneNumber)->toBe('(423)555-0177');

    // Whoever started it already knows: no new-submission alert.
    Notification::assertNothingSent();
});

test('an admin starts a resource request for an assessment\'s victim, with only her name and safe number', function () {
    $admin = staffUser(UserRole::Admin);
    $from = finishedFollowUp(FollowUpKind::CivilianAssessment, $admin, FollowUpStatus::Declined);

    $this->actingAs($admin);
    $page = Livewire::test(ViewFollowUp::class, ['record' => $from->getKey()])
        ->assertActionHasLabel('new_request', 'New resource request for this victim');

    expect($page->instance()->newRequestDefaults())->toBe([
        'FirstName' => STAFF_TEST_PREFIX.'Victim',
        'LastName' => 'Doe',
        'SafePhoneNumber' => '(423)555-0100',
    ]);

    $page->callAction('new_request', data: [
        ...$page->instance()->newRequestDefaults(),
        'Message' => 'Needs shelter information.',
    ])->assertHasNoActionErrors();

    $started = ResourceRequestForm::where('CreatedFromFollowUpID', $from->getKey())->sole();

    // Nothing from the assessment beyond who to reach.
    expect($started->FirstName)->toBe(STAFF_TEST_PREFIX.'Victim')
        ->and($started->SafePhoneNumber)->toBe('(423)555-0100')
        ->and($started->EmailAddress)->toBeNull()
        ->and(json_encode($started->getAttributes()))->not->toContain('Offender');
});

// A request started from a civilian assessment, and that assessment's follow-up.
function requestFromAssessment(): array
{
    $admin = staffUser(UserRole::Admin);
    $from = finishedFollowUp(FollowUpKind::CivilianAssessment, $admin);
    $started = app(FollowUps::class)->startResourceRequest($from, [
        'FirstName' => STAFF_TEST_PREFIX.'Victim',
        'SafePhoneNumber' => '(423)555-0100',
    ], $admin);

    return [$admin, $from, $started];
}

test('an admin sees where a request came from, both ways', function () {
    [$admin, $from, $started] = requestFromAssessment();

    $this->actingAs($admin)
        ->get($started->url())
        ->assertOk()
        ->assertSee('Started by staff')
        ->assertSee('From Civilian Assessment', false)
        ->assertSee($from->url(), false);

    // And the assessment's own page lists the request started from it.
    $this->flushSession()
        ->actingAs($admin)
        ->get($from->url())
        ->assertSee('Requests started from this form')
        ->assertSee($started->url(), false);
});

// Its own test: a test reuses one app across requests, and Filament caches
// the sidebar it built for the first user (a browser request never shares it).
test('a secretary never learns a request came from an assessment', function () {
    [, $from, $started] = requestFromAssessment();

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get($started->url())
        ->assertOk()
        ->assertSee('Started by staff')
        // Visible text: the page's own markup carries a class named hs-assessment-record.
        ->assertDontSeeText('assessment')
        ->assertDontSee($from->url(), false);
});

test('only a final form offers a new request, and only to those allowed', function () {
    $admin = staffUser(UserRole::Admin);
    $secretary = staffUser(UserRole::Secretary);

    $waiting = pestFollowUp(FollowUpKind::ResourceRequest);
    $assessment = finishedFollowUp(FollowUpKind::LawEnforcementAssessment, $admin);
    // Reached isn't final (it can still move on to Closed).
    $reached = pestFollowUp(FollowUpKind::ResourceRequest);
    app(FollowUps::class)->update($reached, FollowUpStatus::Reached, 'Sent the list.', $admin);

    $this->actingAs($admin);
    Livewire::test(ViewFollowUp::class, ['record' => $waiting->getKey()])->assertActionHidden('new_request');
    Livewire::test(ViewFollowUp::class, ['record' => $reached->getKey()])->assertActionHidden('new_request');

    expect(FollowUps::canStartResourceRequest($waiting, $admin))->toBeFalse()
        ->and(FollowUps::canStartResourceRequest($reached->refresh(), $admin))->toBeFalse()
        ->and(FollowUps::canStartResourceRequest($assessment, $admin))->toBeTrue()
        // Secretaries work resource requests only, never assessments.
        ->and(FollowUps::canStartResourceRequest($assessment, $secretary))->toBeFalse()
        ->and(fn () => app(FollowUps::class)->startResourceRequest($assessment, ['FirstName' => 'X', 'SafePhoneNumber' => '(423)555-0100'], $secretary))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(FollowUps::class)->startResourceRequest($waiting, ['FirstName' => 'X', 'SafePhoneNumber' => '(423)555-0100'], $admin))
        ->toThrow(AuthorizationException::class);
});

test('a new request passes the website\'s own checks', function (array $data, string $field) {
    $admin = staffUser(UserRole::Admin);
    $from = finishedFollowUp(FollowUpKind::ResourceRequest, $admin);

    $this->actingAs($admin);
    Livewire::test(ViewFollowUp::class, ['record' => $from->getKey()])
        ->callAction('new_request', data: ['FirstName' => publicFormsRunToken(), ...$data])
        ->assertHasActionErrors([$field]);

    expect(ResourceRequestForm::where('CreatedFromFollowUpID', $from->getKey())->exists())->toBeFalse();
})->with([
    'no way to reach them' => [['EmailAddress' => null, 'SafePhoneNumber' => null], 'EmailAddress'],
    'a number without its area code' => [['SafePhoneNumber' => '555-0100'], 'SafePhoneNumber'],
    'no first name' => [['FirstName' => '', 'EmailAddress' => 'a@b.test'], 'FirstName'],
]);

test('staff-started requests are marked Staff in Submissions', function () {
    $admin = staffUser(UserRole::Admin);
    $from = finishedFollowUp(FollowUpKind::ResourceRequest, $admin);
    $started = app(FollowUps::class)->startResourceRequest($from, [
        'FirstName' => publicFormsRunToken(),
        'EmailAddress' => 'someone@pest.test',
    ], $admin)->resourceRequest;

    $this->actingAs($admin);
    Livewire::test(ListResourceRequests::class)
        ->filterTable('Source', ResourceRequestForm::SOURCE_STAFF)
        ->assertCanSeeTableRecords([$started])
        ->assertCanNotSeeTableRecords([$from->resourceRequest]);
});

test('deleting a request removes its follow-up and history too', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = finishedFollowUp(FollowUpKind::ResourceRequest, $admin);

    $followUp->resourceRequest->delete();

    expect(FollowUp::whereKey($followUp->getKey())->exists())->toBeFalse()
        ->and(DB::connection('Portal')->table('follow_up_entries')->where('FollowUpID', $followUp->getKey())->exists())->toBeFalse();
});
