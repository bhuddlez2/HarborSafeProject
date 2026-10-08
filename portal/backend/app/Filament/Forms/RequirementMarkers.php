<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

/*
How the staff panel marks required and optional inputs. The same rule applies
on the public website (website/frontend FormControls) and in the civilian
assessment (portal/frontend app/page.js):

  - REQUIRED: a red asterisk after the label. Filament does this on its own
    for any field that is ->required(); nothing here is needed for it. The one
    deliberate exception is the sign-in page (Pages/Auth/Login), which turns
    it off with markAsRequired(false). Don't add that anywhere else.
  - OPTIONAL: faded grey "Optional" ghost text inside the box - the HTML
    placeholder, so it disappears as soon as someone types. Text inputs, text
    areas and dropdowns get it automatically. A date has no box to hold ghost
    text (browsers ignore placeholders on native date inputs), so where one
    needs flagging - the offender's date of birth - the same grey "Optional"
    goes after its label via optionalLabel(). File uploads and toggles get no
    optional marker; a toggle always has a value.

Registered once in AppServiceProvider as a default for every TextInput,
Textarea and Select, so new fields follow it without anyone remembering to.
A field that calls ->placeholder() itself overrides the default; don't do that
on an optional field - put an example in ->helperText() instead, so the box
still says "Optional".

Not applied inside table filter cards (AssessmentFilters, ChangeLogFilters,
SelectFilter, ...): every filter is optional by nature, so "Optional" there
would be noise.

A field that is required only in some situations (requiredWith, a confirm
password box) must not show "Optional" - set its placeholder explicitly or
make it ->required() when it truly is. See the password confirmation fields in
StaffAccountResource and OfficerAccountResource.
*/
final class RequirementMarkers
{
    public const OPTIONAL = 'Optional';

    public static function register(): void
    {
        TextInput::configureUsing(fn (TextInput $field) => $field->placeholder(
            static fn (TextInput $component): ?string => self::optionalPlaceholder($component),
        ));

        Textarea::configureUsing(fn (Textarea $field) => $field->placeholder(
            static fn (Textarea $component): ?string => self::optionalPlaceholder($component),
        ));

        // Select's own setUp() sets a "Select an option" placeholder, and this
        // replaces it, so keep that wording for required dropdowns and filters.
        Select::configureUsing(fn (Select $field) => $field->placeholder(
            static fn (Select $component): ?string => $component->isDisabled()
                ? null
                : (self::optionalPlaceholder($component) ?? __('filament-forms::components.select.placeholder')),
        ));
    }

    // The label-side marker for an optional input with no box for ghost text:
    // the label, then "Optional" in the same faded grey as a placeholder.
    public static function optionalLabel(string $label): HtmlString
    {
        return new HtmlString(e($label).' <span class="ms-1 font-normal text-gray-400">'.self::OPTIONAL.'</span>');
    }

    public static function optionalPlaceholder(Field $component): ?string
    {
        if ($component->isRequired() || $component->isDisabled() || self::isTableFilter($component)) {
            return null;
        }

        return self::OPTIONAL;
    }

    // Table filter forms live under these two Livewire properties
    // (Filament\Tables\Concerns\HasFilters).
    private static function isTableFilter(Field $component): bool
    {
        $path = $component->getStatePath();

        return str_starts_with($path, 'tableFilters.') || str_starts_with($path, 'tableDeferredFilters.');
    }
}
