<?php

namespace App\Http\Requests\Public;

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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'FirstName'       => $this->normalizeText($this->input('FirstName')),
            'LastName'        => $this->normalizeText($this->input('LastName')),
            'EmailAddress'    => $this->normalizeText($this->input('EmailAddress')),
            'SafePhoneNumber' => $this->normalizeText($this->input('SafePhoneNumber')),
            'Message'         => $this->normalizeText($this->input('Message')),
        ]);
    }

    public function rules(): array
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
            'SafePhoneNumber' => ['required_without:EmailAddress', 'nullable', 'string', 'max:20', 'regex:/^[0-9+\-.() ]+$/'],

            'ResourceTypeIDs'   => ['nullable', 'array', 'max:8'],
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

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
