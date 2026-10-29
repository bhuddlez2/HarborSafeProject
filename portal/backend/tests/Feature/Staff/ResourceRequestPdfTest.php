<?php

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Models\AssessmentAccessLog;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\ResourceRequestForm;
use App\Services\AssessmentPdf;
use App\Services\FollowUps;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
Follow-up tracking, part 5 (team plan task 16): a resource request as a PDF -
the plain form, nothing from its follow-up - with every download recorded in
assessment_access_log, as is opening its follow-up page (both show the
requester's safe phone number).

App\Services\AssessmentPdf is faked, to see which record it was handed;
real printing is pinned in PdfRenderingTest.
*/

beforeEach(function () {
    $this->instance(AssessmentPdf::class, new class extends AssessmentPdf
    {
        public function render(LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | App\Models\ServiceFeedback $record): string
        {
            return '%PDF-fake';
        }
    });
});

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function requestAccess(ResourceRequestForm $request): Illuminate\Support\Collection
{
    return AssessmentAccessLog::where('ResourceRequestFormID', $request->FormID)->get();
}

test('admins and secretaries download a request, and each download is recorded', function (UserRole $role) {
    $request = pestFollowUp(FollowUpKind::ResourceRequest)->resourceRequest;
    $user = staffUser($role);

    $this->actingAs($user)
        ->get(route('staff.resource-requests.pdf', $request->FormID))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="resource-request-'.substr($request->FormID, 0, 8).'.pdf"');

    $rows = requestAccess($request);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::Download)
        ->and($rows[0]->AccessedBy)->toBe($user->getKey())
        ->and($rows[0]->LawEnforcementDocumentID)->toBeNull()
        ->and($rows[0]->PrivateDocumentID)->toBeNull();
})->with([UserRole::Admin, UserRole::Secretary]);

test('nobody else can download one, and a refusal records nothing', function (Closure $makeUser, int $status) {
    $request = pestFollowUp(FollowUpKind::ResourceRequest)->resourceRequest;

    $this->actingAs($makeUser())
        ->get(route('staff.resource-requests.pdf', $request->FormID))
        ->assertStatus($status);

    expect(requestAccess($request))->toBeEmpty();
})->with([
    'officer' => [fn () => pestAgencyMember(UserRole::LawEnforcement, pestAgency('PDF PD')), 404],
    'police admin' => [fn () => pestAgencyMember(UserRole::PoliceAdmin, pestAgency('PDF PD 2')), 404],
    'inactive secretary' => [fn () => staffUser(UserRole::Secretary, isActive: false), 403],
]);

test('the PDF is the plain form: nothing from its follow-up, nothing about staff', function () {
    $admin = staffUser(UserRole::Admin);
    $from = pestFollowUp(FollowUpKind::ResourceRequest);
    app(FollowUps::class)->update($from, FollowUpStatus::Closed, 'A note that must stay out of the PDF.', $admin);
    $started = app(FollowUps::class)->startResourceRequest($from->refresh(), [
        'FirstName' => publicFormsRunToken(),
        'LastName' => 'Requester',
        'SafePhoneNumber' => '(423)555-0199',
        'Message' => 'Needs a ride to court.',
    ], $admin)->resourceRequest;

    $html = App\Support\RecordPdf::html($started->load(['county', 'resourceTypes']));

    expect($html)
        ->toContain('Resource Request')
        ->toContain(publicFormsRunToken().' Requester')
        ->toContain('(423)555-0199')
        ->toContain('Needs a ride to court.')
        ->not->toContain('A note that must stay out of the PDF.')
        ->not->toContain('Started by staff')
        ->not->toContain('Entered by')
        ->not->toContain('entered by')
        ->not->toContain($admin->name)
        ->not->toContain('Needs Follow-Up')
        ->not->toContain('Log');
});

test('opening a request\'s follow-up page is a recorded view, with Download PDF', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $secretary = staffUser(UserRole::Secretary);

    $this->actingAs($secretary)
        ->get($followUp->url())
        ->assertOk()
        ->assertSee(route('staff.resource-requests.pdf', $followUp->ResourceRequestFormID), false);

    $rows = requestAccess($followUp->resourceRequest);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->Action)->toBe(AssessmentAccess::View)
        ->and($rows[0]->AccessedBy)->toBe($secretary->getKey());
});

test('Submissions -> Resource requests offers the PDF from View too', function () {
    $request = pestFollowUp(FollowUpKind::ResourceRequest)->resourceRequest;
    $this->actingAs(staffUser(UserRole::Secretary));

    Livewire::test(ListResourceRequests::class)
        ->mountAction(TestAction::make('view')->table($request))
        ->assertActionVisible(TestAction::make('download_pdf')->table($request));
});
