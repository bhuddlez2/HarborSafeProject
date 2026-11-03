<?php

namespace App\Http\Requests\Public;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/*
POST /api/public/resource-requests

Unauthenticated by design (see Public_Forms_Backend_Design.md) - authorize()
is always true. This class is the chokepoint for that endpoint's input
safety: baseline shape/type/range checks live in rules() below, and any
additional security-check specs (rate/abuse checks that need more than a
validation rule, content filtering, etc.) should be added here via
withValidator() rather than in the controller.
*/
class ResourceRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public const MAX_RESOURCE_TYPES = 8;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'FirstName'       => $this->normalizeText($this->input('FirstName')),
            'LastName'        => $this->normalizeText($this->input('LastName')),
            'EmailAddress'    => $this->normalizeText($this->input('EmailAddress')),
            'SafePhoneNumber' => $this->normalizeText($this->input('SafePhoneNumber')),
            'Message'         => $this->normalizeText($this->input('Message')),
        ]);

        // Every item is checked (distinct, exists) before the list's own
        // max:8 fails, and distinct compares each item with every other: a
        // 100,000-item list held the server for 30 seconds. One item past
        // the limit is enough for max:8 to refuse it.
        $types = $this->input('ResourceTypeIDs');
        if (is_array($types) && count($types) > self::MAX_RESOURCE_TYPES) {
            $this->merge(['ResourceTypeIDs' => array_slice($types, 0, self::MAX_RESOURCE_TYPES + 1)]);
        }
    }

    public function rules(): array
    {
        return self::fieldRules();
    }

    // The fields' rules, shared with requests staff start in the panel
    // (ResourceRequestForm::recordStaffEntry()), so a staff-started request
    // passes exactly the checks a website one does.
    public static function fieldRules(): array
    {
        return [
            // Contact rule: the form only needs one way to reach the
            // submitter back (see Public_Forms_Backend_Design.md #2) -
            // required_without on both fields means at least one must be
            // present, and prepareForValidation() above makes sure a
            // whitespace-only value doesn't count as "present".
            'FirstName'       => ['required', 'string', 'max:50'],
            'LastName'        => ['nullable', 'string', 'max:50'],
            'EmailAddress'    => ['required_without:SafePhoneNumber', 'nullable', 'email', 'max:250'],
            // A full 10-digit number, area code included (App\Support\PhoneNumber).
            'SafePhoneNumber' => ['required_without:EmailAddress', 'nullable', 'string', 'max:20', PhoneNumber::rule()],

            'ResourceTypeIDs'   => ['nullable', 'array', 'max:'.self::MAX_RESOURCE_TYPES],
            'ResourceTypeIDs.*' => ['integer', 'distinct', 'exists:FeedbackPublic.resources,id'],

            'CountyID' => ['nullable', 'integer', 'exists:FeedbackPublic.counties,id'],
            'Message'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    // Belt-and-suspenders alongside the two required_without rules above:
    // if something ever validates each field individually as "provided"
    // while both are still practically unusable, fail loudly here instead
    // of storing a submission nobody can be reached through.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('EmailAddress') && ! $this->filled('SafePhoneNumber')) {
                $validator->errors()->add('EmailAddress', 'Please provide an email address or a phone number.');
                $validator->errors()->add('SafePhoneNumber', 'Please provide an email address or a phone number.');
            }
        });
    }

    // Non-strings (a list or object sent where text belongs) are left for the
    // `string` rule to refuse with a 422, rather than crashing here.
    public static function normalizeText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
