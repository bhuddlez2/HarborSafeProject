<?php

use Illuminate\Support\Facades\DB;

afterEach(fn () => cleanupPublicFormData());

test('a valid request with email only is accepted', function () {
    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ]);

    $response->assertStatus(201)->assertJsonStructure(['message', 'FormID']);
    expect(array_keys($response->json()))->toEqualCanonicalizing(['message', 'FormID']);

    $this->assertDatabaseHas('resource_request_form', [
        'FormID'          => $response->json('FormID'),
        'FirstName'       => publicFormsRunToken(),
        'SafePhoneNumber' => null,
    ], 'Feedback');
});

test('a valid request with phone only is accepted', function () {
    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'SafePhoneNumber' => '423-555-0100',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('resource_request_form', [
        'FormID'       => $response->json('FormID'),
        'EmailAddress' => null,
    ], 'Feedback');
});

test('a request with neither email nor phone is rejected', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName' => publicFormsRunToken(),
    ])->assertStatus(422)->assertJsonValidationErrors(['EmailAddress', 'SafePhoneNumber']);
});

test('whitespace-only contact fields do not count as provided', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'EmailAddress'    => '   ',
        'SafePhoneNumber' => '   ',
    ])->assertStatus(422)->assertJsonValidationErrors(['EmailAddress', 'SafePhoneNumber']);
});

test('FirstName is required', function () {
    $this->postJson('/api/public/resource-requests', [
        'EmailAddress' => publicFormsEmail(),
    ])->assertStatus(422)->assertJsonValidationErrors('FirstName');
});

test('a malformed email is rejected', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => 'not-an-email',
    ])->assertStatus(422)->assertJsonValidationErrors('EmailAddress');
});

test('a phone number with unexpected characters is rejected', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'SafePhoneNumber' => '<script>alert(1)</script>',
    ])->assertStatus(422)->assertJsonValidationErrors('SafePhoneNumber');
});

test('multiple resource types are stored in the junction table', function () {
    $resourceA = publicFormsMakeResource('Pest Emergency Shelter');
    $resourceB = publicFormsMakeResource('Pest Legal Help');

    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'EmailAddress'    => publicFormsEmail(),
        'ResourceTypeIDs' => [$resourceA, $resourceB],
    ]);

    $response->assertStatus(201);
    $formId = $response->json('FormID');

    $this->assertDatabaseHas('resource_request_resource_types', [
        'FormID' => $formId, 'ResourceTypeID' => $resourceA,
    ], 'Feedback');
    $this->assertDatabaseHas('resource_request_resource_types', [
        'FormID' => $formId, 'ResourceTypeID' => $resourceB,
    ], 'Feedback');
});

test('a request with no resource types selected is still accepted', function () {
    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'EmailAddress'    => publicFormsEmail(),
        'ResourceTypeIDs' => [],
    ]);

    $response->assertStatus(201);

    expect(DB::connection('Feedback')->table('resource_request_resource_types')
        ->where('FormID', $response->json('FormID'))->count())->toBe(0);
});

test('a resource type that does not exist is rejected', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'EmailAddress'    => publicFormsEmail(),
        'ResourceTypeIDs' => [999999],
    ])->assertStatus(422)->assertJsonValidationErrors('ResourceTypeIDs.0');
});

test('duplicate resource type ids are rejected', function () {
    $resourceA = publicFormsMakeResource();

    $this->postJson('/api/public/resource-requests', [
        'FirstName'       => publicFormsRunToken(),
        'EmailAddress'    => publicFormsEmail(),
        'ResourceTypeIDs' => [$resourceA, $resourceA],
    ])->assertStatus(422);
});

test('a valid county is stored', function () {
    $countyId = publicFormsMakeCounty();

    $response = $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
        'CountyID'     => $countyId,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('resource_request_form', [
        'FormID'   => $response->json('FormID'),
        'CountyID' => $countyId,
    ], 'Feedback');
});

test('a county that does not exist is rejected', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
        'CountyID'     => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('CountyID');
});

test('an overlong first name is rejected before it can hit the database', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => str_repeat('a', 51),
        'EmailAddress' => publicFormsEmail(),
    ])->assertStatus(422)->assertJsonValidationErrors('FirstName');
});

test('an overlong message is rejected before it can hit the database', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
        'Message'      => str_repeat('a', 1001),
    ])->assertStatus(422)->assertJsonValidationErrors('Message');
});

test('the 11th submission in a minute is throttled', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/public/resource-requests', [
            'FirstName'    => publicFormsRunToken(),
            'EmailAddress' => publicFormsEmail(),
        ])->assertStatus(201);
    }

    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertStatus(429);
});

test('the restricted FeedbackPublic user cannot read back submissions', function () {
    $this->postJson('/api/public/resource-requests', [
        'FirstName'    => publicFormsRunToken(),
        'EmailAddress' => publicFormsEmail(),
    ])->assertStatus(201);

    expect(fn () => DB::connection('FeedbackPublic')->table('resource_request_form')->count())
        ->toThrow(Illuminate\Database\QueryException::class);
});
