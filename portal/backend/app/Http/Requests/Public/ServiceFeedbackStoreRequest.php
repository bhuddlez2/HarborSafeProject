<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/*
POST /api/public/service-feedback

Unauthenticated by design (see Public_Forms_Backend_Design.md) - authorize()
is always true. This class is the chokepoint for that endpoint's input
safety: baseline shape/type/range checks live in rules() below, and any
additional security-check specs (rate/abuse checks that need more than a
validation rule, content filtering, etc.) should be added here via
withValidator() rather than in the controller.
*/
class ServiceFeedbackStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Comment' => self::normalizeText($this->input('Comment')),
        ]);
    }

    public function rules(): array
    {
        return self::fieldRules();
    }

    /*
    The rules themselves, public and static so the staff panel's "Add paper
    form" action (ServiceFeedbackResource) validates a typed-in paper form with
    exactly these - one list, so the two can never drift apart. Add a rule
    here and both the website and the panel enforce it.
    */
    public static function fieldRules(): array
    {
        return [
            'ServiceID' => ['required', 'integer', 'exists:FeedbackPublic.services,id'],
            'Rating'    => ['required', 'integer', 'between:1,5'],
            'Comment'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    // Trims and collapses a blank/whitespace-only string down to null so
    // "nullable" rules and required_without-style checks see it as absent
    // rather than as a non-empty string. Public for the same reason as
    // fieldRules(): paper entries are normalised the same way.
    public static function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
