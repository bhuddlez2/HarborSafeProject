<?php

namespace App\Filament\Assessments;

use App\Filament\Forms\DateOfBirthInput;
use App\Support\AssessmentFields;
use App\Support\PhoneNumber;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;

/*
The rules for a law-enforcement assessment's details, shared by the officer
wizard (NewAssessment) and the Edit pop-up (AssessmentEditForm), so an edit is
held to the same standard as the original submission. Each screen adds only
its own look (labels, widths, placeholders); never a rule.

The relationship fields are shared the same way, in RelationshipFields.
*/
final class AssessmentInputs
{
    // Letters, spaces, apostrophes and hyphens.
    public const NAME_PATTERN = "/^[a-zA-Z\s'-]+$/";

    public static function name(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(50)
            ->regex(self::NAME_PATTERN)
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : $state)
            ->validationMessages([
                'required' => 'Required',
                'regex' => 'Cannot contain numbers or symbols',
            ]);
    }

    // Victim's: required. Offender's: optional.
    public static function dateOfBirth(string $name, string | Htmlable $label, bool $required): TextInput
    {
        return DateOfBirthInput::make($name, $label)->required($required);
    }

    public static function sex(string $name): Select
    {
        return Select::make($name)
            ->label('Sex')
            ->native()
            ->options(AssessmentFields::SEX_OPTIONS)
            ->required()
            ->validationMessages([
                'required' => 'Select an option',
                'in' => 'Select an option',
            ]);
    }

    // A full 10-digit number, shown as (xxx)xxx-xxxx while typing. Changing it
    // unticks the safe-number confirmation, which never carries over from one
    // number to another.
    public static function safePhone(string $label): TextInput
    {
        return TextInput::make('VictimSafePhoneNumber')
            ->label($label)
            ->tel()
            ->mask(PhoneNumber::MASK)
            ->required()
            ->maxLength(20)
            ->rule(PhoneNumber::rule())
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Set $set) => $set('SafePhoneConfirmed', false))
            ->validationMessages(['required' => 'Required']);
    }

    /*
    A second check that the safe number really is safe: must be ticked to
    save. Never stored - it only makes sure the officer knows the number
    must be safe to call.
    */
    public static function safePhoneConfirmation(): Checkbox
    {
        return Checkbox::make('SafePhoneConfirmed')
            ->label(fn (Get $get): string => 'I have confirmed that '
                .(filled($get('VictimSafePhoneNumber')) ? $get('VictimSafePhoneNumber') : 'the safe phone number')
                .' is safe to use to contact the victim.')
            ->accepted()
            ->live()
            ->dehydrated(false)
            ->validationMessages(['accepted' => 'Confirm the safe phone number before submitting.']);
    }
}
