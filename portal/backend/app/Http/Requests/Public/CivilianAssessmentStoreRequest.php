<?php

namespace App\Http\Requests\Public;

use App\Enums\OffenderRelationship;
use App\Support\DateOfBirth;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
POST /api/public/civilian-assessments

The public website's lethality assessment (website/frontend/src/app/assessment),
submitted in one request: the 11 answers, the victim and offender, and - only
when someone reports for another person and declines anonymity - the
submitter's contact details.

Unauthenticated by design, so authorize() is always true and this class is the
chokepoint for the endpoint's input safety. Everything the controller writes
comes out of validated(), and the controller maps it to columns by hand, so a
key the rules don't name never reaches the database: the record ids
(AssessmentDocID, SubmissionID, DocumentID) are generated on the server and
can't be supplied or pointed at an existing record.

No rule here may touch the database (`exists:`, `unique:`): the endpoint writes
through PortalPublic, whose MySQL user can't SELECT.
*/
class CivilianAssessmentStoreRequest extends FormRequest
{
    public const RISK_INDICATORS = 11;

    // The same codes as the officer wizard (NewAssessment) and the labels in
    // App\Support\AssessmentFields.
    public const SEXES = ['M', 'F', 'O'];


    private const TEXT_FIELDS = [
        'VictimFirstName', 'VictimLastName', 'VictimSafePhoneNumber',
        'OffenderFirstName', 'OffenderLastName', 'OffenderVictimRelationshipOther',
        'SubmitterFirstName', 'SubmitterLastName', 'SubmitterEmail', 'SubmitterPhoneNumber',
        'VictimDOB', 'OffenderDOB',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::TEXT_FIELDS as $field) {
            if ($this->has($field)) {
                $normalized[$field] = self::normalizeText($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $identified = $this->submitterIdentified();

        $answers = [];
        foreach (range(1, self::RISK_INDICATORS) as $n) {
            $answers["RiskIndicator{$n}"] = ['required', 'boolean'];
        }

        // Dates of birth are within the last 125 years, App\Support\DateOfBirth -
        // the same rule the website checks before sending (lib/validators.js
        // dateOfBirthError). The victim's is required, the offender's optional.
        $optionalDate = ['nullable', ...DateOfBirth::rules()];
        // A full 10-digit number, area code included - App\Support\PhoneNumber,
        // the same rule the website checks before sending (lib/validators.js
        // isValidPhone).
        $phone = ['string', 'max:20', PhoneNumber::rule()];

        return [
            'ForWhom'   => ['required', Rule::in(['self', 'other'])],
            // Only asked when reporting for someone else.
            'Anonymous' => ['required_if:ForWhom,other', 'prohibited_if:ForWhom,self', 'nullable', 'boolean'],

            ...$answers,

            'VictimFirstName'       => ['required', 'string', 'max:50'],
            'VictimLastName'        => ['required', 'string', 'max:50'],
            'VictimSex'             => ['required', Rule::in(self::SEXES)],
            // Required, as on the website and in the officer wizard; checked
            // here too as a backstop.
            'VictimDOB'             => ['required', ...DateOfBirth::rules()],
            // Required so Harbor Safe can follow up with the victim,
            // anonymous submissions included.
            'VictimSafePhoneNumber' => ['required', ...$phone],

            'OffenderFirstName'     => ['required', 'string', 'max:50'],
            // Optional: someone reporting for another person may not know it
            // (Burchfield B29). Still required in the officer wizard.
            'OffenderLastName'      => ['nullable', 'string', 'max:50'],
            'OffenderSex'           => ['required', Rule::in(self::SEXES)],
            'OffenderDOB'           => $optionalDate,
            ...OffenderRelationship::validationRules(),

            // Contact details only when the submitter chose to give them; an
            // anonymous (or self) submission that carries them is refused
            // rather than silently stored or dropped.
            'SubmitterFirstName'   => [Rule::requiredIf($identified), Rule::prohibitedIf(! $identified), 'nullable', 'string', 'max:50'],
            'SubmitterLastName'    => [Rule::requiredIf($identified), Rule::prohibitedIf(! $identified), 'nullable', 'string', 'max:50'],
            'SubmitterEmail'       => [Rule::prohibitedIf(! $identified), 'nullable', 'email', 'max:100'],
            'SubmitterPhoneNumber' => [Rule::prohibitedIf(! $identified), 'nullable', ...$phone],
        ];
    }

    public function messages(): array
    {
        return [
            'VictimDOB.before_or_equal'   => DateOfBirth::FUTURE_MESSAGE,
            'OffenderDOB.before_or_equal' => DateOfBirth::FUTURE_MESSAGE,
            'VictimDOB.after_or_equal'    => DateOfBirth::TOO_OLD_MESSAGE,
            'OffenderDOB.after_or_equal'  => DateOfBirth::TOO_OLD_MESSAGE,
        ];
    }

    // Someone reporting for another person who said no to anonymity: the only
    // case in which a submitter-info row is written.
    public function submitterIdentified(): bool
    {
        return $this->input('ForWhom') === 'other'
            && $this->filled('Anonymous')
            && ! $this->boolean('Anonymous');
    }

    private static function normalizeText(mixed $value): mixed
    {
        // Non-strings (arrays, numbers) are left for the `string` rules to
        // refuse rather than being coerced here.
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
