<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

/*
Encryption of sensitive columns before they reach the database (Finishing
Touches item 14).

Encrypted (each model lists its own in an ENCRYPTED constant, cast through
App\Models\Concerns\EncryptsSensitiveFields):
  - both assessments' victim and offender details: names, sex, dates of birth,
    relationship and its "Other" text, the victim's safe phone number
    (law_enforcement_assessment, _private_assessment);
  - the civilian submitter's name, email and phone (_submitter_info);
  - a resource request's name, email, phone and message (resource_request_form);
  - the old and new values in an edit's field log (assessment_change_log);
  - follow-up notes (follow_up_entries.Note).
Left plain: the 11 yes/no answers, ids, times, submitted_by, edit reasons,
service feedback, and everything about staff accounts (passwords are hashed,
which is stronger than encryption).

The key is DATA_ENCRYPTION_KEY (config app.data_key), never APP_KEY. There is
no fallback: without it every read or write of an encrypted column throws, so
nothing is ever stored in the clear or under the wrong key. Old keys go in
DATA_ENCRYPTION_PREVIOUS_KEYS (comma-separated) and still decrypt.

What this costs: the database can't search, sort or count these columns. The
places that need to (table search, the name filters, Analytics' sex, age and
relationship charts) decrypt the rows the viewer may see in PHP instead -
App\Support\EncryptedSearch and AssessmentStats. Fine at Harbor Safe's size;
it would slow down in the hundreds of thousands of records.
*/
final class SensitiveData
{
    public const KEY_ENV = 'DATA_ENCRYPTION_KEY';

    /** @var array{0: string, 1: EncrypterContract}|null */
    private static ?array $built = null;

    // The encrypter for the configured key, or one that refuses everything
    // when no usable key is set. Rebuilt only when the configuration changes.
    public static function encrypter(): EncrypterContract
    {
        $config = serialize([config('app.data_key'), config('app.previous_data_keys'), config('app.cipher')]);

        if (self::$built === null || self::$built[0] !== $config) {
            self::$built = [$config, self::build()];
        }

        return self::$built[1];
    }

    private static function build(): EncrypterContract
    {
        $key = self::parseKey((string) config('app.data_key'));

        if ($key === '') {
            return new MissingDataKey(self::KEY_ENV.' is not set, so sensitive records can\'t be read or saved. '
                .'Run "php artisan data-key:generate" on a new install, or restore the key this database was encrypted with.');
        }

        $cipher = (string) config('app.cipher');

        if (! Encrypter::supported($key, $cipher)) {
            return new MissingDataKey(self::KEY_ENV.' is not a valid '.$cipher.' key. Generate one with "php artisan data-key:generate".');
        }

        return (new Encrypter($key, $cipher))->previousKeys(array_map(
            fn (string $previous): string => self::parseKey($previous),
            array_values(array_filter((array) config('app.previous_data_keys'))),
        ));
    }

    public static function encrypt(?string $value): ?string
    {
        return $value === null ? null : self::encrypter()->encrypt($value, false);
    }

    public static function decrypt(?string $value): ?string
    {
        return $value === null ? null : self::encrypter()->decrypt($value, false);
    }

    // True when the value is ciphertext this key (or a previous one) opens.
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        try {
            self::decrypt($value);

            return true;
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return false;
        }
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
    }

    private static function parseKey(string $key): string
    {
        $key = trim($key);

        return Str::startsWith($key, 'base64:') ? (string) base64_decode(Str::after($key, 'base64:'), true) : $key;
    }
}
