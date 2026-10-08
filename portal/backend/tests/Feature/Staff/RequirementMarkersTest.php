<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Forms\RequirementMarkers;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\NewAssessment;
use App\Filament\Resources\AssessmentReview\Pages\ListAssessmentReview;
use App\Filament\Resources\LawEnforcementAssessments\Pages\ListLawEnforcementAssessments;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use App\Filament\Resources\StaffAccounts\Pages\EditStaffAccount;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

/*
Required inputs carry Filament's red asterisk; optional text inputs, text areas
and dropdowns show "Optional" ghost text (App\Filament\Forms\RequirementMarkers).
Asserted on the field objects rather than the HTML, because the rule is "which
fields get which marker", and that is what breaks when someone adds a field
with its own placeholder or turns the asterisk off.
*/

afterEach(fn () => cleanupStaffData());

$optional = fn (Field $field): bool => $field->getPlaceholder() === RequirementMarkers::OPTIONAL
    && ! $field->isMarkedAsRequired();

$required = fn (Field $field): bool => $field->isMarkedAsRequired()
    && $field->getPlaceholder() !== RequirementMarkers::OPTIONAL;

test('event fields: required ones get the asterisk, optional ones the ghost text', function () use ($optional, $required) {
    $this->actingAs(staffUser(UserRole::Secretary));

    $form = Livewire::test(ManageEvents::class)->mountAction('create');

    foreach (['title', 'starts_at'] as $field) {
        $form->assertSchemaComponentExists($field, checkComponentUsing: $required);
    }

    // Text input, text area, dropdown - and the three that used to hold an
    // example in the box, which now lives in the helper text.
    foreach (['summary', 'description', 'category_id', 'recurrence', 'registration_label', 'registration_url'] as $field) {
        $form->assertSchemaComponentExists($field, checkComponentUsing: $optional);
    }
});

test('a dropdown that is required keeps its usual prompt', function () {
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Markers PD')));

    Livewire::test(NewAssessment::class)
        ->assertSchemaComponentExists('VictimSex', checkComponentUsing: fn (Select $field): bool => $field->getPlaceholder() === 'Select'
            && $field->isMarkedAsRequired());
});

test('the New Assessment wizard marks its required fields', function () use ($required) {
    // The wizard used to switch the asterisk off; it no longer does.
    $this->actingAs(pestAgencyMember(UserRole::LawEnforcement, pestAgency('Markers PD')));

    $wizard = Livewire::test(NewAssessment::class);

    // The safe phone number is required so the organisation can follow up.
    foreach (['VictimFirstName', 'VictimLastName', 'VictimDOB', 'VictimSafePhoneNumber', 'OffenderFirstName', 'OffenderVictimRelationship'] as $field) {
        $wizard->assertSchemaComponentExists($field, checkComponentUsing: $required);
    }

    // A date can't hold ghost text, so the offender's date of birth says
    // "Optional" after its label instead.
    $wizard->assertSchemaComponentExists('OffenderDOB', checkComponentUsing: fn (Field $field): bool => str_contains((string) $field->getLabel(), RequirementMarkers::OPTIONAL)
        && ! $field->isMarkedAsRequired());
});

test('the officer edit pop-up flags the offender\'s date of birth as optional', function () {
    $officer = pestAgencyMember(UserRole::LawEnforcement, pestAgency('Markers PD'));
    $assessment = pestOfficerAssessment($officer, 'Markers');

    $this->actingAs($officer);

    Livewire::test(ListLawEnforcementAssessments::class)
        ->mountAction(TestAction::make(EditAction::class)->table($assessment))
        ->assertSchemaComponentExists('OffenderDOB', checkComponentUsing: fn (Field $field): bool => str_contains((string) $field->getLabel(), RequirementMarkers::OPTIONAL)
            && ! $field->isMarkedAsRequired());
});

test('the sign-in page shows no asterisks', function () {
    // The one deliberate exception: two obviously needed fields.
    Livewire::test(Login::class)
        ->assertSchemaComponentExists('email', checkComponentUsing: fn (Field $field): bool => $field->isRequired() && ! $field->isMarkedAsRequired())
        ->assertSchemaComponentExists('password', checkComponentUsing: fn (Field $field): bool => $field->isRequired() && ! $field->isMarkedAsRequired());
});

test('filter cards never say "Optional"', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $fields = Livewire::test(ListAssessmentReview::class)
        ->instance()
        ->getTableFiltersForm()
        ->getFlatFields(withHidden: true);

    $inputs = collect($fields)->filter(fn ($field): bool => $field instanceof TextInput || $field instanceof Select);

    expect($inputs)->not->toBeEmpty()
        ->and($inputs->filter(fn (Field $field): bool => $field->getPlaceholder() === RequirementMarkers::OPTIONAL))->toBeEmpty();
});

test('confirm password is required on create and never "Optional" on edit', function () use ($required) {
    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreateStaffAccount::class)
        ->assertSchemaComponentExists('passwordConfirmation', checkComponentUsing: $required);

    Livewire::test(EditStaffAccount::class, ['record' => staffUser(UserRole::Secretary)->getKey()])
        // Optional to change the password at all...
        ->assertSchemaComponentExists('password', checkComponentUsing: fn (Field $field): bool => $field->getPlaceholder() === RequirementMarkers::OPTIONAL)
        // ...but confirming one you typed is not optional.
        ->assertSchemaComponentExists('passwordConfirmation', checkComponentUsing: fn (Field $field): bool => $field->getPlaceholder() === null
            && ! $field->isMarkedAsRequired());
});
