<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Analytics\Pages\FeedbackAnalytics;
use App\Filament\Clusters\Analytics\Pages\OfficerAssessmentAnalytics;
use App\Filament\Resources\StaffAccounts\Pages\CreateStaffAccount;
use Livewire\Livewire;

/*
"* Required" (Burchfield B23/B30, 2026-10-28): wherever the panel shows a red
asterisk, a key just above the buttons says what it means.

Filament's own forms get it from theme.css ("* Required" section), drawn only
while an asterisk is on screen: at the bottom of a pop-up, of the current step
of a form with steps, or above a page form's buttons. CSS can't be run here, so
this pins the rules and the Filament markup they hang on, which an upgrade could
rename. The website's version is covered by tests/forms.spec.js and
tests/assessment.spec.js (Playwright).
*/

afterEach(fn () => cleanupStaffData());

test('theme.css draws the note for pop-ups, steps and page forms', function () {
    $css = file_get_contents(resource_path('css/filament/staff/theme.css'));
    $marks = ':has(.fi-fo-field-label-required-mark, .fi-fieldset-label-required-mark)';

    expect($css)
        ->toContain(".fi-modal-content:not(:has(.fi-sc-wizard)){$marks}::after")
        ->toContain(".hs-lap-wizard .fi-sc-wizard-step.fi-active{$marks}::after")
        ->toContain('.fi-sc-wizard:not(.hs-lap-wizard):has(.fi-sc-wizard-step.fi-active :is(.fi-fo-field-label-required-mark, .fi-fieldset-label-required-mark)) .fi-sc-wizard-footer::before')
        ->toContain(".fi-sc-form:not(:has(.fi-sc-wizard)){$marks} .fi-sc-actions[id\$=\"form-actions\"]::before")
        ->toContain('content: "* Required" / "";')
        ->toContain('.hs-required-note');
});

test('the Filament markup the rules depend on is still there', function () {
    $vendor = base_path('vendor/filament');

    expect(file_get_contents("{$vendor}/forms/resources/views/components/field-wrapper.blade.php"))
        ->toContain('fi-fo-field-label-required-mark')
        ->and(file_get_contents("{$vendor}/support/resources/views/components/fieldset.blade.php"))
        ->toContain('fi-fieldset-label-required-mark')
        ->and(file_get_contents("{$vendor}/support/resources/views/components/modal/index.blade.php"))
        ->toContain('fi-modal-content')
        ->and(file_get_contents("{$vendor}/schemas/src/Components/Wizard/Step.php"))
        ->toContain("'fi-sc-wizard-step'")
        ->toContain("'fi-active'")
        ->and(file_get_contents("{$vendor}/schemas/src/Components/Wizard.php"))
        ->toContain('class="fi-sc-wizard-footer"')
        ->and(file_get_contents("{$vendor}/schemas/src/Components/Form.php"))
        ->toContain("'fi-sc-form'");
});

test('a page form puts its buttons where the rule looks for them', function () {
    $this->actingAs(staffUser(UserRole::SuperAdmin));

    Livewire::test(CreateStaffAccount::class)
        ->assertSeeHtml('fi-fo-field-label-required-mark')
        ->assertSeeHtml('form-actions" class="fi-sc-actions"');
});

test('the Analytics custom range, which draws its own asterisks, shows the note', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    // Every filter is optional (FT32), so the Filters panel has none.
    Livewire::test(OfficerAssessmentAnalytics::class)
        ->assertSeeHtml('aria-controls="analytics-filters"')
        ->assertDontSeeHtml('hs-required-note')
        ->assertDontSeeHtml('fi-fo-field-label-required-mark');

    // From and To are required on the custom range.
    Livewire::test(FeedbackAnalytics::class)
        ->assertDontSeeHtml('hs-required-note')
        ->call('setRange', 'custom')
        ->assertSeeHtml('<p class="hs-required-note" aria-hidden="true">* Required</p>');
});
