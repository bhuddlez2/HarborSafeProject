<?php

use App\Enums\OffenderRelationship;
use App\Enums\UserRole;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Models\AssessmentChangeLog;
use App\Support\AssessmentFields;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
"Relationship to victim" as one fixed list (App\Enums\OffenderRelationship,
roadmap Phase P): the same options in the wizard, the officer Edit pop-up and
the civilian form (which fetches them), stored as codes, with "Other" needing
a typed detail. The server refuses anything not on the list.
*/

afterEach(fn () => cleanupStaffData());

test('every option label capitalises each word', function () {
    foreach (OffenderRelationship::cases() as $case) {
        // Each word, including after a hyphen or opening bracket.
        preg_match_all('/(?:^|[\s(\-\/])([a-z])/', $case->label(), $lowercaseStarts);

        expect($lowercaseStarts[1])->toBe([], "\"{$case->label()}\" has a word starting in lower case");
    }
});

test('the civilian form can fetch the list, in order, without signing in', function () {
    $this->getJson('/api/public/relationships')
        ->assertOk()
        ->assertExactJson(collect(OffenderRelationship::cases())
            ->map(fn (OffenderRelationship $case): array => ['value' => $case->value, 'label' => $case->label()])
            ->all());
});

function relationshipErrors(array $input): array
{
    return Validator::make($input, OffenderRelationship::validationRules())->errors()->toArray();
}

test('the server accepts a code from the list', function () {
    expect(relationshipErrors(['OffenderVictimRelationship' => 'dating_partner']))->toBe([]);
});

test('the server refuses free text, a missing value, and Other without a detail', function (array $input, string $field) {
    expect(relationshipErrors($input))->toHaveKey($field);
})->with([
    'free text' => [['OffenderVictimRelationship' => 'Boyfriend'], 'OffenderVictimRelationship'],
    'nothing' => [[], 'OffenderVictimRelationship'],
    'Other with no detail' => [['OffenderVictimRelationship' => 'other'], 'OffenderVictimRelationshipOther'],
    'a detail without Other' => [['OffenderVictimRelationship' => 'spouse', 'OffenderVictimRelationshipOther' => 'Neighbour'], 'OffenderVictimRelationshipOther'],
    'a detail over 50 characters' => [['OffenderVictimRelationship' => 'other', 'OffenderVictimRelationshipOther' => str_repeat('a', 51)], 'OffenderVictimRelationshipOther'],
]);

test('both APIs use those rules', function () {
    // The civilian endpoint is public; free text is refused there.
    $this->postJson('/api/private-assessments', ['OffenderVictimRelationship' => 'Boyfriend'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('OffenderVictimRelationship');

    // The officer endpoint's rules (behind auth) are the same list, now
    // required - checked at the source rather than through a token.
    $source = file_get_contents(app_path('Http/Controllers/LawEnforcementAssessmentController.php'));
    expect(substr_count($source, 'OffenderRelationship::validationRules('))->toBe(2);
});

test('the wizard offers the list, and asks for detail only for Other', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Relationship PD')));

    Livewire::test(NewAssessment::class)
        ->assertSchemaComponentExists('OffenderVictimRelationship', checkComponentUsing: fn (Select $field): bool => $field->getOptions() === OffenderRelationship::options()
            && $field->isRequired())
        ->assertSchemaComponentExists('OffenderVictimRelationshipOther', checkComponentUsing: fn (Field $field): bool => $field->isHidden())
        ->set('data.OffenderVictimRelationship', 'other')
        ->assertSchemaComponentExists('OffenderVictimRelationshipOther', checkComponentUsing: fn (Field $field): bool => ! $field->isHidden()
            && $field->isRequired());
});

test('an officer can change Other to a listed option, which clears the detail and logs readable labels', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Relationship PD'));
    $assessment = pestOfficerAssessment($officer, 'Rel', [
        'OffenderVictimRelationship' => 'other',
        'OffenderVictimRelationshipOther' => 'Partner',
    ]);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'OffenderVictimRelationship' => 'live_in_partner',
            'reason' => 'Confirmed with the victim',
        ])
        ->assertHasNoActionErrors();

    $assessment->refresh();

    expect($assessment->OffenderVictimRelationship)->toBe('live_in_partner')
        ->and($assessment->OffenderVictimRelationshipOther)->toBeNull();

    $logged = AssessmentChangeLog::query()->where('DocumentID', $assessment->getKey())->get()->keyBy('ChangeField');

    expect(AssessmentFields::value('OffenderVictimRelationship', $logged['OffenderVictimRelationship']->NewValue))->toBe('Live-In Partner (Not Married)')
        ->and(AssessmentFields::value('OffenderVictimRelationship', $logged['OffenderVictimRelationship']->PreviousValue))->toBe('Other (Please Specify)')
        ->and($logged['OffenderVictimRelationshipOther']->PreviousValue)->toBe('Partner');
});

test('Other cannot be saved without a detail in the Edit pop-up', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Relationship PD'));
    $assessment = pestOfficerAssessment($officer, 'Rel', ['OffenderVictimRelationship' => 'spouse']);

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->callAction(TestAction::make(EditAction::class)->table($assessment), [
            'OffenderVictimRelationship' => 'other',
            'OffenderVictimRelationshipOther' => '',
            'reason' => 'Trying Other',
        ])
        ->assertHasActionErrors(['OffenderVictimRelationshipOther' => 'required']);
});

test('records read as labels, "Other: <detail>", or unmapped text as typed', function () {
    expect(OffenderRelationship::display('co_parent'))->toBe('Co-Parent')
        ->and(OffenderRelationship::display('other', 'Neighbour'))->toBe('Other: Neighbour')
        ->and(OffenderRelationship::display('other'))->toBe('Other')
        ->and(OffenderRelationship::display('Something typed long ago'))->toBe('Something typed long ago')
        ->and(OffenderRelationship::display(null))->toBeNull();
});

test('the one-time mapping turns old free text into codes, keeping anything unclear as Other', function (string $typed, ?string $code) {
    $migration = require database_path('migrations/2026_10_09_120000_relationship_to_victim_as_a_list.php');
    $codeFor = (new ReflectionMethod($migration, 'codeFor'));

    expect($codeFor->invoke($migration, $typed))->toBe($code);
})->with([
    ['Spouse', 'spouse'],
    ['  husband ', 'spouse'],
    ['Ex-Wife', 'former_spouse'],
    ['Boyfriend', 'dating_partner'],
    ['ex-girlfriend', 'former_dating_partner'],
    ['Roommate', 'household_member'],
    ['Sister', 'family_member'],
    // Ambiguous: could be dating or live-in, so it is not guessed.
    ['Partner', null],
    ['Neighbour', null],
]);
