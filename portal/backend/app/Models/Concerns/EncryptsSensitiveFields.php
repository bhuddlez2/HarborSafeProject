<?php

namespace App\Models\Concerns;

use App\Support\PhoneNumber;

/*
Casts every column in the model's ENCRYPTED constant with Laravel's
`encrypted` cast, under DATA_ENCRYPTION_KEY (App\Support\SensitiveData). The
model reads and writes plain values; only the database sees ciphertext.

Phone numbers are formatted to (xxx)xxx-xxxx before they are encrypted: list
them in phoneNumberFields(). (An Attribute mutator would skip the cast, and a
custom cast would lose the encrypted cast's "unchanged value is not a change"
check that AssessmentEditor relies on.)

The database can't search these columns: use App\Support\EncryptedSearch.
*/
trait EncryptsSensitiveFields
{
    public function initializeEncryptsSensitiveFields(): void
    {
        $this->mergeCasts(array_fill_keys(static::ENCRYPTED, 'encrypted'));
    }

    /** @return list<string> */
    protected function phoneNumberFields(): array
    {
        return [];
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->phoneNumberFields(), true)) {
            $value = PhoneNumber::format($value === null ? null : (string) $value);
        }

        return parent::setAttribute($key, $value);
    }
}
