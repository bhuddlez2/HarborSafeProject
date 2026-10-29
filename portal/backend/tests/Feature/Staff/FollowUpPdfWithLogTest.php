<?php

use App\Enums\AssessmentAccess;
use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\AssessmentAccessLog;
use App\Models\FollowUp;
use App\Services\AssessmentPdf;
use App\Services\FollowUps;
use App\Support\RecordPdf;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
"Download PDF with log" on a form's follow-up page (Burchfield B43,
2026-10-28; FollowUpPdfController): the form plus its follow-up Log, for when
Harbor Safe has to provide the record with its history. The plain "Download
PDF" stays the form only and stays the main button. Only those who work the
form get it; every download is recorded as its own kind, which a police
admin sees as a plain download.
*/

afterEach(function () {
    cleanupCivilianAssessments();
    cleanupPublicFormData();
    cleanupStaffData();
});

function withLogNote(FollowUp $followUp, string $note): void
{
    app(FollowUps::class)->update($followUp, FollowUpStatus::AttemptedContact, $note, staffUser(UserRole::Admin));
}

test('the export is the form, an "Internal" banner and the whole Log; the plain PDF has neither', function () {
    $followUp = pestFollowUp(FollowUpKind::CivilianAssessment);
    withLogNote($followUp, 'Left a voicemail at the safe number.');

    $withLog = RecordPdf::htmlWithLog($followUp->refresh());
    $plain = RecordPdf::html($followUp->form());

    expect($withLog)
        ->toContain('Internal: includes follow-up notes', 'Follow-Up Log', 'Civilian Assessment · Current Status: Attempted Contact')
        ->toContain('Submitted', 'Needs Follow-Up → Attempted Contact', 'Left a voicemail at the safe number.')
        ->and($plain)->not->toContain('Internal: includes follow-up notes', 'Follow-Up Log', 'Left a voicemail');
});

test('a 2,000-character note prints in full, running onto the next page', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $note = str_repeat('Called and spoke with the requester about next steps. ', 37);
    withLogNote($followUp, mb_substr($note, 0, FollowUps::NOTE_MAX));

    $bytes = app(AssessmentPdf::class)->renderWithLog($followUp->refresh());

    expect($bytes)->toStartWith('%PDF')
        ->and(substr_count($bytes, '/Type /Page') - substr_count($bytes, '/Type /Pages'))->toBeGreaterThan(1);
});

test('admins download any kind; Staff resource requests only; each download is recorded as "with log"', function () {
    $officer = pestFollowUp(FollowUpKind::LawEnforcementAssessment);
    $civilian = pestFollowUp(FollowUpKind::CivilianAssessment);
    $request = pestFollowUp(FollowUpKind::ResourceRequest);
    $admin = staffUser(UserRole::Admin);
    $staff = staffUser(UserRole::Secretary);

    foreach ([$officer, $civilian, $request] as $followUp) {
        $this->actingAs($admin)->get(route('staff.follow-ups.pdf-with-log', $followUp->getKey()))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    $this->actingAs($staff)->get(route('staff.follow-ups.pdf-with-log', $request->getKey()))->assertOk();
    $this->actingAs($staff)->get(route('staff.follow-ups.pdf-with-log', $officer->getKey()))->assertNotFound();
    $this->actingAs($staff)->get(route('staff.follow-ups.pdf-with-log', $civilian->getKey()))->assertNotFound();

    expect(AssessmentAccessLog::where('AccessedBy', $admin->getKey())->pluck('Action')->map->value->unique()->values()->all())
        ->toBe([AssessmentAccess::DownloadWithLog->value])
        ->and(AssessmentAccessLog::where('AccessedBy', $admin->getKey())->count())->toBe(3);
});

test('officers and police admins never get it, even for their own agency\'s assessment', function () {
    $w = pestTwoAgencies();
    $followUp = app(FollowUps::class)->open(FollowUpKind::LawEnforcementAssessment, $w->assessmentA->DocumentID);

    foreach ([$w->officerA, $w->policeAdminA] as $user) {
        $this->actingAs($user)->get(route('staff.follow-ups.pdf-with-log', $followUp->getKey()))->assertNotFound();
    }

    expect(AssessmentAccessLog::where('LawEnforcementDocumentID', $w->assessmentA->DocumentID)->count())->toBe(0);
});

test('Download PDF is the one-click button; the export with the log is in the arrow menu beside it', function () {
    $followUp = pestFollowUp(FollowUpKind::ResourceRequest);
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(App\Filament\Resources\FollowUps\Pages\ViewFollowUp::class, ['record' => $followUp->getKey()])
        ->assertActionVisible('download_pdf')
        ->assertActionHasUrl('download_pdf', route('staff.resource-requests.pdf', $followUp->ResourceRequestFormID))
        ->assertActionVisible(['download_pdf_with_log'])
        ->assertActionHasUrl(['download_pdf_with_log'], route('staff.follow-ups.pdf-with-log', $followUp->getKey()));

    $this->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
        ->assertOk()
        ->assertSeeInOrder(['Download PDF', 'More download options', 'Download PDF with log']);
});

test('a police admin sees an admin\'s download with the log as a plain "Downloaded PDF"', function () {
    $w = pestTwoAgencies();
    $followUp = app(FollowUps::class)->open(FollowUpKind::LawEnforcementAssessment, $w->assessmentA->DocumentID);
    $this->actingAs(staffUser(UserRole::Admin))->get(route('staff.follow-ups.pdf-with-log', $followUp->getKey()))->assertOk();

    // The record's Log pop-up on Assessment Review.
    $this->actingAs($w->policeAdminA);
    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))
        ->assertMountedActionModalSee('Downloaded PDF')
        ->assertMountedActionModalDontSee('Downloaded PDF with log');

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ListAssessmentReview::class)
        ->mountAction(TestAction::make('changeLog')->table($w->assessmentA))
        ->assertMountedActionModalSee('Downloaded PDF with log');
});
