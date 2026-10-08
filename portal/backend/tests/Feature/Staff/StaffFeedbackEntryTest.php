<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Models\FeedbackScan;
use App\Models\ServiceFeedback;
use App\Support\Timezones;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
Service-feedback forms filled in by an admin or secretary ("New feedback form"
on the Service feedback tab) - typically ones that arrived on paper, though
nothing on screen says so. Phase M's gate, as tests: admins and secretaries
can add one, marked Source = staff with its date and time and who entered it;
the website's own rules reject the same bad input here; scans are stored
beside the feedback and opened only by admins and secretaries; online
feedback still has no edit; resource requests still have no create action.
*/

afterEach(function () {
    cleanupPublicFormData();
    cleanupStaffData();
});

// Real bytes (see ContentAuthoringTest on why not UploadedFile::fake()).
// Starts with the PDF magic number, so the mime type sniffs as a PDF.
function feedbackScanUpload(): TestingFile
{
    $resource = tmpfile();
    fwrite($resource, '%PDF-1.4'.str_repeat('s', 64 * 1024));

    return new TestingFile(staffRunToken().'-scan.pdf', $resource);
}

// A staff entry with a scan, written directly - for the tests about who may
// open it, which should not depend on the create pop-up.
function staffEntryWithScan(): ServiceFeedback
{
    $scan = FeedbackScan::store('%PDF-1.4 test scan', staffRunToken().'-scan.pdf', 'application/pdf');

    return ServiceFeedback::recordStaffEntry([
        'ServiceID' => publicFormsMakeService(),
        'Rating' => 4,
        'SubmissionDate' => '2026-09-15 14:30',
        'ScanFileID' => $scan->getKey(),
    ], staffUser(UserRole::Secretary));
}

test('admins and secretaries can fill in a feedback form, marked as staff', function (UserRole $role) {
    $user = staffUser($role);
    $this->actingAs($user);
    $serviceId = publicFormsMakeService();

    Livewire::test(ListServiceFeedback::class)
        // Just a feedback form to the person entering it - no mention of paper.
        ->assertActionHasLabel('create', 'New feedback form')
        ->callAction('create', data: [
            'ServiceID' => $serviceId,
            'Rating' => 2,
            'SubmissionDate' => '2026-09-15 14:30',
            'Comment' => "  Waited a long time.  \n",
        ])
        ->assertHasNoActionErrors();

    $feedback = ServiceFeedback::where('ServiceID', $serviceId)->sole();

    expect($feedback->Source)->toBe(ServiceFeedback::SOURCE_STAFF)
        ->and($feedback->EnteredBy)->toBe($user->getKey())
        ->and($feedback->enteredByName())->toBe($user->name)
        // Typed as 2:30 pm Eastern (EDT in September), stored as UTC.
        ->and($feedback->SubmissionDate->toDateTimeString())->toBe('2026-09-15 18:30:00')
        // Normalised exactly as the website normalises a comment.
        ->and($feedback->Comment)->toBe('Waited a long time.')
        ->and($feedback->ScanFileID)->toBeNull();

    // Shown back in Eastern with a time stamp, like online feedback, and a
    // Staff badge.
    Livewire::test(ListServiceFeedback::class)
        ->assertCanSeeTableRecords([$feedback])
        ->assertTableColumnFormattedStateSet('Source', 'Staff', $feedback)
        ->assertTableColumnFormattedStateSet('SubmissionDate', 'Sep 15, 2026 2:30 pm', $feedback);
})->with([UserRole::Admin, UserRole::Secretary]);

test('the form defaults to now, shown in Eastern', function () {
    $this->actingAs(staffUser(UserRole::Secretary));

    $form = Livewire::test(ListServiceFeedback::class)->mountAction('create');

    // The field holds Eastern wall-clock time; read it as such and it is now.
    $default = Carbon::parse($form->get('mountedActions.0.data.SubmissionDate'), Timezones::DISPLAY);

    expect(abs($default->diffInMinutes(now())))->toBeLessThanOrEqual(1);
});

test('a staff entry is held to the website\'s own rules', function (array $override, string $field) {
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ListServiceFeedback::class)
        ->callAction('create', data: [
            'ServiceID' => publicFormsMakeService(),
            'Rating' => 3,
            'SubmissionDate' => '2026-09-15 14:30',
            ...$override,
        ])
        ->assertHasActionErrors([$field]);
})->with([
    'no service' => [['ServiceID' => null], 'ServiceID'],
    'a service that does not exist' => [['ServiceID' => 99999999], 'ServiceID'],
    'no rating' => [['Rating' => null], 'Rating'],
    'a rating above 5' => [['Rating' => 6], 'Rating'],
    'a comment over 1000 characters' => [['Comment' => str_repeat('a', 1001)], 'Comment'],
    'no date' => [['SubmissionDate' => null], 'SubmissionDate'],
    'a date in the future' => [['SubmissionDate' => '2999-01-01 10:00'], 'SubmissionDate'],
]);

test('a scan is stored beside the feedback, not with website content', function () {
    $this->actingAs(staffUser(UserRole::Secretary));
    $serviceId = publicFormsMakeService();

    Livewire::test(ListServiceFeedback::class)
        ->callAction('create', data: [
            'ServiceID' => $serviceId,
            'Rating' => 5,
            'SubmissionDate' => '2026-09-15 14:30',
            'ScanFileID' => feedbackScanUpload(),
        ])
        ->assertHasNoActionErrors();

    $feedback = ServiceFeedback::where('ServiceID', $serviceId)->sole();
    $scan = FeedbackScan::find($feedback->ScanFileID);

    expect($scan)->not->toBeNull()
        ->and($scan->mime_type)->toBe('application/pdf')
        ->and(strlen($scan->contents()))->toBe($scan->size_bytes)
        // Not in content_files, whose route any panel user can open.
        ->and(App\Models\ContentFile::find($feedback->ScanFileID))->toBeNull();
});

test('an admin or secretary can open a scan, uncached', function (UserRole $role) {
    $feedback = staffEntryWithScan();

    $response = $this->actingAs(staffUser($role))
        ->get(route('staff.feedback-scans.show', ['file' => $feedback->ScanFileID]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('cache-control'))->toContain('no-store')
        ->toContain('private');
})->with([UserRole::Admin, UserRole::Secretary]);

test('nobody else can open a scan', function (UserRole $role) {
    $feedback = staffEntryWithScan();

    $this->actingAs(staffUser($role))
        ->get(route('staff.feedback-scans.show', ['file' => $feedback->ScanFileID]))
        ->assertNotFound();
})->with([UserRole::LawEnforcement, UserRole::PoliceAdmin]);

test('an anonymous visitor is sent to sign in rather than shown a scan', function () {
    $feedback = staffEntryWithScan();

    $this->get(route('staff.feedback-scans.show', ['file' => $feedback->ScanFileID]))
        ->assertRedirect(filament()->getPanel('staff')->getLoginUrl());
});

test('deleting a staff entry deletes its scan', function () {
    $feedback = staffEntryWithScan();
    $scanId = $feedback->ScanFileID;

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListServiceFeedback::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($feedback));

    expect(ServiceFeedback::find($feedback->getKey()))->toBeNull()
        ->and(FeedbackScan::find($scanId))->toBeNull();
});

test('bulk delete removes scans too', function () {
    $first = staffEntryWithScan();
    $second = staffEntryWithScan();
    $scanIds = [$first->ScanFileID, $second->ScanFileID];

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListServiceFeedback::class)
        ->selectTableRecords([$first, $second])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(FeedbackScan::query()->whereKey($scanIds)->count())->toBe(0);
});

test('feedback from the website stays online, whatever the request claims', function () {
    // The public endpoint creates from its validated input only, and Source
    // and EnteredBy are not mass-assignable - so neither can be forged.
    $serviceId = publicFormsMakeService();

    $formId = $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating' => 4,
        'Source' => ServiceFeedback::SOURCE_STAFF,
        'EnteredBy' => 1,
    ])->assertCreated()->json('FormID');

    $feedback = ServiceFeedback::find($formId);

    expect($feedback->Source)->toBe(ServiceFeedback::SOURCE_ONLINE)
        ->and($feedback->EnteredBy)->toBeNull();
});

test('feedback still cannot be edited, and resource requests cannot be created', function () {
    $feedback = staffEntryWithScan();

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListServiceFeedback::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($feedback));

    // Resource requests come only from the website, for every role.
    Livewire::test(ListResourceRequests::class)
        ->assertActionDoesNotExist('create');
});

test('officers and police admins cannot reach the feedback tab at all', function (UserRole $role) {
    $this->actingAs(staffUser($role));

    Livewire::test(ListServiceFeedback::class)->assertForbidden();
})->with([UserRole::LawEnforcement, UserRole::PoliceAdmin]);
