<?php

use Illuminate\Support\Facades\DB;

afterEach(fn () => cleanupPublicFormData());

test('valid feedback is accepted and returns a bare confirmation', function () {
    $serviceId = publicFormsMakeService();

    $response = $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 5,
        'Comment'   => 'Everyone was kind and it helped a lot.',
    ]);

    $response->assertStatus(201)->assertJsonStructure(['message', 'FormID']);

    // The response is a confirmation plus the generated id - never the
    // stored record itself.
    expect(array_keys($response->json()))->toEqualCanonicalizing(['message', 'FormID']);

    $this->assertDatabaseHas('service_feedback', [
        'FormID'    => $response->json('FormID'),
        'ServiceID' => $serviceId,
        'Rating'    => 5,
        'Comment'   => 'Everyone was kind and it helped a lot.',
    ], 'Feedback');
});

test('comment is optional', function () {
    $serviceId = publicFormsMakeService();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 3,
    ])->assertStatus(201);
});

test('a blank comment is stored as null rather than an empty string', function () {
    $serviceId = publicFormsMakeService();

    $response = $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 3,
        'Comment'   => '   ',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('service_feedback', [
        'FormID'  => $response->json('FormID'),
        'Comment' => null,
    ], 'Feedback');
});

test('rating outside 1-5 is rejected', function () {
    $serviceId = publicFormsMakeService();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 7,
    ])->assertStatus(422)->assertJsonValidationErrors('Rating');
});

test('rating is required', function () {
    $serviceId = publicFormsMakeService();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
    ])->assertStatus(422)->assertJsonValidationErrors('Rating');
});

test('a ServiceID that does not exist is rejected', function () {
    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => 999999,
        'Rating'    => 4,
    ])->assertStatus(422)->assertJsonValidationErrors('ServiceID');
});

test('an overlong comment is rejected before it can hit the database', function () {
    $serviceId = publicFormsMakeService();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 4,
        'Comment'   => str_repeat('a', 1001),
    ])->assertStatus(422)->assertJsonValidationErrors('Comment');
});

test('the 11th submission in a minute is throttled', function () {
    $serviceId = publicFormsMakeService();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/public/service-feedback', [
            'ServiceID' => $serviceId,
            'Rating'    => 5,
        ])->assertStatus(201);
    }

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 5,
    ])->assertStatus(429);
});

test('the restricted FeedbackPublic user cannot read back submissions', function () {
    $serviceId = publicFormsMakeService();

    $this->postJson('/api/public/service-feedback', [
        'ServiceID' => $serviceId,
        'Rating'    => 5,
    ])->assertStatus(201);

    expect(fn () => DB::connection('FeedbackPublic')->table('service_feedback')->count())
        ->toThrow(Illuminate\Database\QueryException::class);
});
