<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\StringEncrypter;
use RuntimeException;

// Stands in for the data encrypter when DATA_ENCRYPTION_KEY is missing or
// unusable: every call throws, so a sensitive column is never written in the
// clear or read as gibberish. See App\Support\SensitiveData.
final class MissingDataKey implements Encrypter, StringEncrypter
{
    public function __construct(private readonly string $message) {}

    public function encrypt(#[\SensitiveParameter] $value, $serialize = true)
    {
        $this->fail();
    }

    public function decrypt($payload, $unserialize = true)
    {
        $this->fail();
    }

    public function encryptString(#[\SensitiveParameter] $value)
    {
        $this->fail();
    }

    public function decryptString($payload)
    {
        $this->fail();
    }

    public function getKey()
    {
        $this->fail();
    }

    public function getAllKeys()
    {
        $this->fail();
    }

    public function getPreviousKeys()
    {
        $this->fail();
    }

    private function fail(): never
    {
        throw new RuntimeException($this->message);
    }
}
