<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Notification;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs against the shared local database, so without this
        // every test that submits a form would put bell alerts in the real
        // local accounts (App\Enums\NewSubmission). Tests that need a real
        // alert row swap the dispatcher back (see SubmissionAlertsTest).
        Notification::fake();
    }
}
