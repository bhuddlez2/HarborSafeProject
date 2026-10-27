<?php

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\FollowUps\Pages\ViewFollowUp;
use App\Models\AssessmentAccessLog;
use App\Services\FollowUps;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Textarea;
use Livewire\Livewire;

/*
Follow-up tracking, part 2 (team plan task 16): the Follow-ups queue, each
form's follow-up page, and the Log. Who sees what is FollowUp::visibleTo() -
admins every kind, secretaries resource requests only, police nothing.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

// The queue sorted oldest first would put these tests' new forms behind the
// local database's real ones; today's submissions only keeps them on page one.
function followUpQueue(): \Livewire\Features\SupportTesting\Testable
{
    $today = now(App\Support\Timezones::DISPLAY)->toDateString();

    return Livewire::test(ListFollowUps::class)
        ->filterTable('date_submitted', ['from' => $today, 'until' => $today]);
}

test('the queue shows admins every kind of form, secretaries resource requests only', function () {
    $officerAssessment = pestFollowUp(FollowUpKind::LawEnforcementAssessment);
    $civilian = pestFollowUp(FollowUpKind::CivilianAssessment);
    $request = pestFollowUp(FollowUpKind::ResourceRequest);

    $this->actingAs(staffUser(UserRole::Admin));
    followUpQueue()
        ->assertCanSeeTableRecords([$officerAssessment, $civilian, $request])
        ->assertTableColumnVisible('Kind');

    $this->actingAs(staffUser(UserRole::Secretary));
    followUpQueue()
        ->assertCanSeeTableRecords([$request])
        ->assertCanNotSeeTableRecords([$officerAssessment, $civilian])
        // A secretary only ever sees one kind, so no Type column or filter.
        ->assertTableColumnHidden('Kind');
});

test('officers and police admins can\'t reach the queue or any follow-up page', function (Closure $makeUser) {
    $followUp = pestFollowUp(FollowUpKind::LawEnforcementAssessment);
    $this->actingAs($makeUser());

    $this->get(FollowUpResource::getUrl())->assertForbidden();
    // The record lookup is scoped, so for them the page doesn't exist.
    $this->get(FollowUpResource::getUrl('view', ['record' => $followUp]))->assertNotFound();
})->with([
    'officer' => fn () => pestAgencyMember(UserRole::LawEnforcement, pestAgency('Queue PD')),
    'police admin' => fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('Queue PD 2')),
]);

test('each status has its own tab, Needs follow-up first', function () {
    $admin = staffUser(UserRole::Admin);
    $waiting = pestFollowUp(FollowUpKind::CivilianAssessment);
    $reached = pestFollowUp(FollowUpKind::LawEnforcementAssessment);
    app(FollowUps::class)->update($reached, FollowUpStatus::Reached, 'Spoke with her.', $admin);

    $this->actingAs($admin);

    $list = followUpQueue()
        ->assertSet('activeTab', FollowUpStatus::NeedsFollowUp->value)
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$reached]);

    expect(array_values(array_map(fn ($tab) => $tab->getLabel(), $list->instance()->getTabs())))
        ->toBe(['Needs follow-up', 'Attempted contact', 'Reached', 'Could not reach', 'Declined']);

    $list->set('activeTab', FollowUpStatus::Reached->value)
        ->assertCanSeeTableRecords([$reached])
        ->assertCanNotSeeTableRecords([$waiting]);
});

test('a secretary can\'t open an assessment\'s follow-up page', function () {
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
        ->assertNotFound();
});

test('the follow-up page shows the whole form and its history, and opening it is a logged view', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);
    app(FollowUps::class)->update($followUp, FollowUpStatus::AttemptedContact, 'Left a voicemail.', $admin);

    $this->actingAs($admin)
        ->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
        ->assertOk()
        ->assertSee(STAFF_TEST_PREFIX.'Victim Doe')
        ->assertSee('(423)555-0100')
        ->assertSee('Have they ever used a weapon against you', false)
        ->assertSee('Needs follow-up → Attempted contact')
        ->assertSee('Left a voicemail.')
        // The plain form as a PDF, through its own logged download route.
        ->assertSee(route('staff.civilian-assessments.pdf', $followUp->PrivateDocumentID), false);

    $views = AssessmentAccessLog::where('PrivateDocumentID', $followUp->PrivateDocumentID)->get();

    expect($views)->toHaveCount(1)
        ->and($views[0]->Action)->toBe(AssessmentAccess::View)
        // Opening the page never moves the form.
        ->and($followUp->refresh()->Status)->toBe(FollowUpStatus::AttemptedContact);
});

test('a resource request\'s follow-up page shows the request, for a secretary too', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);

    $this->actingAs(staffUser(UserRole::Secretary))
        ->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
        ->assertOk()
        ->assertSee(publicFormsRunToken().' Requester')
        ->assertSee('(423)555-0177')
        ->assertSee($followUp->resourceRequest->EmailAddress);
});

test('Update moves the form with a note, or adds a note on its own', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);
    $this->actingAs($admin);

    $page = fn () => Livewire::test(ViewFollowUp::class, ['record' => $followUp->getKey()]);

    // No note: refused, nothing changes.
    $page()->callAction('update', data: ['status' => 'attempted_contact', 'note' => ''])
        ->assertHasActionErrors(['note' => 'required']);
    expect($followUp->refresh()->Status)->toBe(FollowUpStatus::NeedsFollowUp);

    $page()->callAction('update', data: ['status' => 'attempted_contact', 'note' => 'Left a voicemail.'])
        ->assertHasNoActionErrors();
    $page()->callAction('update', data: ['status' => 'keep', 'note' => 'Texted the safe number too.'])
        ->assertHasNoActionErrors();

    $followUp->refresh();

    expect($followUp->Status)->toBe(FollowUpStatus::AttemptedContact)
        ->and($followUp->LastUpdatedBy)->toBe($admin->getKey())
        ->and($followUp->entries->pluck('Note')->all())->toBe(['Left a voicemail.', 'Texted the safe number too.']);
});

test('Update only offers the moves this form may make', function () {
    $admin = staffUser(UserRole::Admin);
    $request = pestFollowUp(FollowUpKind::ResourceRequest);
    $assessment = pestFollowUp(FollowUpKind::CivilianAssessment);
    app(FollowUps::class)->update($request, FollowUpStatus::Reached, 'Sent the list.', $admin);
    app(FollowUps::class)->update($assessment, FollowUpStatus::Reached, 'Spoke with her.', $admin);

    $this->actingAs($admin);
    $options = fn ($followUp): array => array_keys(
        Livewire::test(ViewFollowUp::class, ['record' => $followUp->getKey()])->instance()->updateStatusOptions(),
    );

    // A final resource request: notes only. A final assessment: the other finals.
    expect($options($request))->toBe(['keep'])
        ->and($options($assessment))->toBe(['keep', 'could_not_reach', 'declined']);
});

test('the queue\'s Log shows the history from submission to now', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    app(FollowUps::class)->update($followUp, null, 'Called, no answer.', $admin);
    app(FollowUps::class)->update($followUp, FollowUpStatus::Reached, 'Sent the shelter list.', $admin);

    $this->actingAs($admin);

    followUpQueue()
        ->set('activeTab', FollowUpStatus::Reached->value)
        ->mountAction(TestAction::make('log')->table($followUp))
        ->assertMountedActionModalSee([
            'Submitted',
            'Note (Needs follow-up)',
            'Called, no answer.',
            'Needs follow-up → Reached',
            'Sent the shelter list.',
            $admin->name,
        ]);
});

test('Update Follow-Up shows the current status and how long the form has been in it', function () {
    $admin = staffUser(UserRole::Admin);
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);
    $this->actingAs($admin);

    // Never moved: counted from submission, in hours under a day.
    $this->travel(5)->hours();
    expect($followUp->refresh()->timeInStatus())->toBe('5 Hours');

    $this->travel(1)->hours();
    $this->travel(3)->days();
    expect($followUp->timeInStatus())->toBe('3 Days');

    // A status change restarts the count...
    app(FollowUps::class)->update($followUp, FollowUpStatus::AttemptedContact, 'Left a voicemail.', $admin);
    expect($followUp->refresh()->timeInStatus())->toBe('0 Hours');

    // ...and a note on its own doesn't, though it moves Last updated.
    $this->travel(1)->hours();
    app(FollowUps::class)->update($followUp, null, 'Texted the safe number too.', $admin);
    $this->travel(1)->days();
    expect($followUp->refresh()->timeInStatus())->toBe('1 Day');

    Livewire::test(ViewFollowUp::class, ['record' => $followUp->getKey()])
        ->assertActionExists('update', fn (Action $action): bool => $action->getLabel() === 'Update Follow-Up'
            && $action->getModalHeading() === 'Update Follow-Up'
            && $action->getModalDescription() === 'Current Status: Attempted contact · Current Days in Status: 1 Day')
        ->mountAction('update')
        // No helper text: the asterisk says it's required, the box gives the limit.
        ->assertFormFieldExists('note', fn (Textarea $field): bool => $field->isRequired()
            && $field->getChildSchema($field::BELOW_CONTENT_SCHEMA_KEY) === null
            && $field->getPlaceholder() === '2,000 Character Limit');
});
