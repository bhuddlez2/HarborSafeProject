<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
Helpers for the /api/public/* feature tests in Feature/Public.

Those tests hit the real Feedback/FeedbackPublic connections - there's no
sqlite stand-in configured for the app's named connections, and the two are
separate MySQL sessions, so test transactions don't work here: rows written
on one connection wouldn't be visible to the other, and the controllers'
DB::disconnect('FeedbackPublic') would tear down an open transaction
mid-test anyway. So these tests commit for real and clean up after
themselves - everything they create is tagged, either by run token (written
into FirstName) or by the throwaway lookup row it references, and
cleanupPublicFormData() removes it in an afterEach hook.

Assertions read through the *Feedback* (full access) connection, never
FeedbackPublic: the restricted user deliberately has no SELECT on the
submission tables, which is itself asserted by the "cannot read back
submissions" tests.
*/

function publicFormsRunToken(): string
{
    static $token = null;

    return $token ??= 'PestRun' . Illuminate\Support\Str::random(8);
}

function publicFormsRegistry(): stdClass
{
    static $registry = null;

    if ($registry === null) {
        $registry = new stdClass();
        $registry->services = [];
        $registry->resources = [];
        $registry->counties = [];
    }

    return $registry;
}

function publicFormsMakeService(string $name = 'Pest Test Service'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('services')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->services[] = $id;

    return $id;
}

function publicFormsMakeResource(string $name = 'Pest Test Resource'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('resources')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->resources[] = $id;

    return $id;
}

function publicFormsMakeCounty(string $name = 'Pest Test County'): int
{
    $id = Illuminate\Support\Facades\DB::connection('Feedback')
        ->table('counties')->insertGetId(['Name' => $name]);
    publicFormsRegistry()->counties[] = $id;

    return $id;
}

function publicFormsEmail(): string
{
    return 'pest-' . Illuminate\Support\Str::random(12) . '@example.com';
}

function cleanupPublicFormData(): void
{
    $registry = publicFormsRegistry();
    $feedback = Illuminate\Support\Facades\DB::connection('Feedback');

    // Submissions first - resource_request_resource_types cascades off
    // resource_request_form, so deleting the parent clears the junction rows.
    $feedback->table('resource_request_form')->where('FirstName', publicFormsRunToken())->delete();

    if ($registry->services !== []) {
        $feedback->table('service_feedback')->whereIn('ServiceID', $registry->services)->delete();
        $feedback->table('services')->whereIn('id', $registry->services)->delete();
    }

    if ($registry->resources !== []) {
        $feedback->table('resources')->whereIn('id', $registry->resources)->delete();
    }

    if ($registry->counties !== []) {
        $feedback->table('counties')->whereIn('id', $registry->counties)->delete();
    }

    $registry->services = [];
    $registry->resources = [];
    $registry->counties = [];
}
