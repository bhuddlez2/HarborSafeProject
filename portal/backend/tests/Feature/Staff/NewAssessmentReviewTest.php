<?php

use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
The officer wizard's Review & Submit, laid out like the civilian review: an
Edit button on each section and a Change button on each answer, coming
straight back to the review. Once the last question has been answered, every
step's Continue (after its usual checks) and every answer return to the
review rather than walking on through the form; and the first question has a
Back to the steps before it, which used to be unreachable from there.

The jumps between steps happen in Filament's wizard in the browser; these
tests pin what the server decides: where Continue sends the wizard, what an
answer does, and what the review offers.
*/

afterEach(fn () => cleanupStaffData());

function reviewWizard(): Testable
{
    test()->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Review PD')));

    return Livewire::test(NewAssessment::class);
}

/** @return array{wizard: string, steps: list<string>} */
function wizardKeys(Testable $page): array
{
    $wizard = $page->instance()->form->getComponent(fn ($component): bool => $component instanceof Wizard);

    $steps = collect($wizard->getChildSchema()->getComponents())
        ->filter(fn ($component): bool => $component instanceof Step)
        ->map(fn (Step $step): string => $step->getKey())
        ->values()
        ->all();

    return ['wizard' => $wizard->getKey(), 'steps' => $steps];
}

// Continue on a step, as Filament's button does it.
function continueFrom(Testable $page, int $stepIndex): Testable
{
    $keys = wizardKeys($page);

    return $page->call('callSchemaComponentMethod', $keys['wizard'], 'nextStep', [
        'currentStepIndex' => $stepIndex,
        'currentStepKey' => $keys['steps'][$stepIndex],
    ]);
}

function answerEveryQuestion(Testable $page): Testable
{
    foreach (array_keys(NewAssessment::QUESTIONS) as $id) {
        $page->call('answerQuestion', $id % 2 === 0);
    }

    return $page;
}

const VICTIM = [
    'VictimFirstName' => 'Review',
    'VictimLastName' => 'Check',
    'VictimDOB' => '01/01/1990',
    'VictimSex' => 'F',
    'VictimSafePhoneNumber' => '423-555-0150',
];

test('before the review, Continue on the victim step goes on to the offender', function () {
    $page = reviewWizard()->fillForm(VICTIM);
    $keys = wizardKeys($page);

    continueFrom($page, 1)->assertDispatched('next-wizard-step', step: $keys['steps'][2]);
});

test('answering the last question opens the review and remembers it was reached', function () {
    $page = answerEveryQuestion(reviewWizard()->assertSet('reviewReached', false));

    $page->assertSet('reviewReached', true)
        ->assertSet('questionIndex', 10)
        ->assertSet('data.RiskIndicator2', true)
        ->assertSet('data.RiskIndicator11', false);
});

test('after the review, Continue on a step goes straight back to it, once that step is valid', function () {
    $page = answerEveryQuestion(reviewWizard())->fillForm(VICTIM);
    $keys = wizardKeys($page);

    continueFrom($page, 1)
        ->assertDispatched('next-wizard-step', step: $keys['steps'][5], currentStep: $keys['steps'][1])
        ->assertNotDispatched('next-wizard-step', step: $keys['steps'][2]);

    // Still checked first: a step with an error stays put.
    $broken = answerEveryQuestion(reviewWizard())->fillForm([...VICTIM, 'VictimFirstName' => '']);

    continueFrom($broken, 1)
        ->assertHasFormErrors(['VictimFirstName'])
        ->assertNotDispatched('next-wizard-step');
});

test('Change opens one question, and answering it goes straight back', function () {
    $page = answerEveryQuestion(reviewWizard());

    $page->call('editAnswer', 0)->assertSet('questionIndex', 0)
        ->call('answerQuestion', true)
        ->assertSet('questionIndex', 0)
        ->assertSet('data.RiskIndicator1', true)
        ->assertSet('data.RiskIndicator2', true);

    // Out-of-range indexes are ignored.
    $page->call('editAnswer', 99)->assertSet('questionIndex', 0)
        ->call('editAnswer', -1)->assertSet('questionIndex', 0);
});

test('the review has an Edit on each section and a Change on each answer', function () {
    $page = answerEveryQuestion(reviewWizard()->fillForm(VICTIM));

    $page->assertSee('Use Edit or Change to fix anything before you submit.')
        ->assertSeeHtml('aria-label="Edit the victim&#039;s details"')
        ->assertSeeHtml('aria-label="Edit the offender&#039;s details"')
        ->assertSeeHtml('goToStep(getSteps()[1])')
        ->assertSeeHtml('goToStep(getSteps()[2])')
        ->assertSeeHtml('aria-label="Change answer to question 1"')
        ->assertSeeHtml('aria-label="Change answer to question 11"')
        ->assertSeeHtml('$wire.editAnswer(10).then(() => goToStep(getSteps()[4]))')
        // The officer wording stays, and yes answers stay counted.
        ->assertSee('is safe to use to contact the victim.')
        ->assertSee('5 of 11 yes')
        ->assertSee('Return to review', false);
});

test('the first question has a Back to the steps before it', function () {
    reviewWizard()
        ->assertSet('questionIndex', 0)
        ->assertSeeHtml('x-on:click="goToPreviousStep()"');
});

test('starting a new assessment forgets the review', function () {
    answerEveryQuestion(reviewWizard())
        ->assertSet('reviewReached', true)
        ->call('startNewAssessment')
        ->assertSet('reviewReached', false)
        ->assertSet('questionIndex', 0);
});
