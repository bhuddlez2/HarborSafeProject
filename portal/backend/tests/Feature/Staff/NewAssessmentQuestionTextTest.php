<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Models\AssessmentAnswers;
use App\Models\FollowUp;
use App\Models\LawEnforcementAssessment;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
The officer wizard asks its questions with the offender's first name, keeping
"you" because the officer reads them aloud to the victim - word for word the
website's wording for a report for oneself (personalTemplates in
website/frontend/src/app/lib/lethality-questions.js). Display only: answers
are stored by number and NewAssessment::QUESTIONS stays the generic list.
Also the wizard's wording about when the assessment is saved (#31).
*/

afterEach(fn () => cleanupStaffData());

// The website's "Myself" column with an offender named John, exactly.
const ASKED_WITH_JOHN = [
    1 => 'Has John ever used a weapon against you or threatened you with a weapon?',
    2 => 'Has John threatened to kill you or your children?',
    3 => 'Has John ever tried to choke (strangle) you?',
    4 => 'Does John have a gun or can John get one easily?',
    5 => 'Is there an active order of protection against John?',
    6 => 'Is John a sex offender?',
    7 => 'Do you have injuries from previous incidents?',
    8 => 'Is John a serial abuser?',
    9 => 'Is John affiliated with gangs or criminal organizations?',
    10 => 'Does John have any involvement in human trafficking?',
    11 => 'Are there children involved who are at risk?',
];

function questionTextWizard(array $data = []): Testable
{
    test()->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Question Text PD')));

    return Livewire::test(NewAssessment::class)->fillForm($data);
}

function questionTextFullForm(array $overrides = []): array
{
    return [
        'VictimFirstName' => 'Review',
        'VictimLastName' => 'Check',
        'VictimDOB' => '1990-01-01',
        'VictimSex' => 'F',
        'VictimSafePhoneNumber' => '423-555-0150',
        'OffenderFirstName' => 'John',
        'OffenderLastName' => 'Roe',
        'OffenderSex' => 'M',
        'OffenderVictimRelationship' => 'spouse',
        ...collect(range(1, 11))->mapWithKeys(fn (int $id): array => ["RiskIndicator{$id}" => $id === 1])->all(),
        ...$overrides,
    ];
}

test('with an offender first name, every question reads as the website asks a report for oneself', function () {
    foreach (ASKED_WITH_JOHN as $id => $text) {
        expect(NewAssessment::questionText($id, 'John'))->toBe($text);
    }

    // Trimmed, like the website.
    expect(NewAssessment::questionText(1, "  John \t"))->toBe(ASKED_WITH_JOHN[1]);
});

test('without a first name, or with only spaces, every question is the generic text', function () {
    expect(NewAssessment::QUESTIONS)->toHaveCount(11);

    foreach (array_keys(NewAssessment::QUESTIONS) as $id) {
        foreach ([null, '', '   ', "\t \n", "\u{00A0}"] as $blank) {
            expect(NewAssessment::questionText($id, $blank))->toBe(NewAssessment::QUESTIONS[$id]);
        }
    }
});

test('the questions and review steps ask with the offender first name only and keep "you"', function () {
    $page = questionTextWizard(questionTextFullForm());

    // The questions step shows question 1; the review lists all 11.
    $page->assertSeeHtml('aria-live="polite"')
        ->assertSee(ASKED_WITH_JOHN[1])
        ->assertDontSee(NewAssessment::QUESTIONS[1]);

    foreach (ASKED_WITH_JOHN as $text) {
        $page->assertSee($text);
    }

    // Never the last name, and never the victim's name, in a question.
    foreach (['Has John Roe', 'Does John Roe', 'Is John Roe', 'against John Roe', 'can John Roe', 'against Review', 'threatened Review', 'Does Review'] as $wrong) {
        $page->assertDontSee($wrong);
    }
});

test('a blank or spaces-only offender first name shows the generic questions', function (string $blank) {
    $page = questionTextWizard(questionTextFullForm(['OffenderFirstName' => $blank]));

    foreach (NewAssessment::QUESTIONS as $text) {
        $page->assertSee($text);
    }

    $page->assertDontSee('Has Roe');
})->with(['empty' => '', 'spaces' => '   ']);

test('changing the offender name changes the questions', function () {
    questionTextWizard(questionTextFullForm())
        ->assertSee(ASKED_WITH_JOHN[1])
        ->fillForm(['OffenderFirstName' => 'Mike'])
        ->assertSee('Has Mike ever used a weapon against you or threatened you with a weapon?')
        ->assertSee('Is there an active order of protection against Mike?')
        ->assertDontSee('Has John');
});

test('the name is printed escaped, never as HTML', function () {
    questionTextWizard(questionTextFullForm(['OffenderFirstName' => '<b>Jo</b>']))
        ->assertSeeHtml('Has &lt;b&gt;Jo&lt;/b&gt; ever used a weapon')
        ->assertDontSeeHtml('Has <b>Jo</b>');
});

test('the stored answers and the record keep the generic wording', function () {
    $page = questionTextWizard(questionTextFullForm(['SafePhoneConfirmed' => true]))
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    $assessment = LawEnforcementAssessment::where('submitted_by', auth()->id())->firstOrFail();

    // Answers by number; the names are only in the victim/offender fields.
    expect($assessment->OffenderFirstName)->toBe('John')
        ->and(AssessmentAnswers::whereKey($assessment->AssessmentDocID)->value('RiskIndicator1'))->toBeTruthy();
});

test('the wizard says the assessment is saved only at Submit', function () {
    questionTextWizard(questionTextFullForm())
        ->assertSee('Nothing is saved to the assessment record until you press Submit at the end.')
        ->assertSee('If you close this tab before then, your answers are gone.')
        ->assertSee('Nothing is saved to the assessment record until you press Submit assessment.')
        ->set('data.SafePhoneConfirmed', true)
        ->call('create')
        ->assertSee('The assessment has been saved to the assessment record.')
        ->assertDontSee('saved successfully');
});

test('a failed save writes nothing, says so, and keeps the answers', function () {
    $fail = true;
    Event::listen('eloquent.creating: '.LawEnforcementAssessment::class, function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('Simulated failure after the answers were written.');
        }
    });

    $answers = AssessmentAnswers::count();
    $assessments = LawEnforcementAssessment::count();
    $followUps = FollowUp::count();

    try {
        questionTextWizard(questionTextFullForm(['SafePhoneConfirmed' => true]))
            ->call('create')
            ->assertSet('submitted', false)
            ->assertSee('The assessment could not be submitted. Nothing was saved. Your answers are still here. Please try again.')
            ->assertSet('data.OffenderFirstName', 'John')
            ->assertSet('data.RiskIndicator1', true);
    } finally {
        $fail = false;
    }

    // The answers row written first was rolled back with the failed assessment.
    expect(AssessmentAnswers::count())->toBe($answers)
        ->and(LawEnforcementAssessment::count())->toBe($assessments)
        ->and(FollowUp::count())->toBe($followUps);
});
