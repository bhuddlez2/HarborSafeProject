<?php

afterEach(fn () => cleanupPublicFormData());

/*
The website's Contact page builds its dropdowns and checkboxes straight from
these three endpoints (website/frontend/src/app/lib/forms.js), so the shape
here is a contract: an array of { id, Name } and nothing else. ChangeDate is
bookkeeping for whoever maintains the lookup tables - the public site has no
use for it and shouldn't be handed it.
*/

test('services are listed as id and Name only', function () {
    $id = publicFormsMakeService('Pest Listed Service');

    $response = $this->getJson('/api/public/services');

    $response->assertStatus(200);

    $row = collect($response->json())->firstWhere('id', $id);
    expect($row)->not->toBeNull()
        ->and(array_keys($row))->toEqualCanonicalizing(['id', 'Name'])
        ->and($row['Name'])->toBe('Pest Listed Service');
});

test('resources are listed as id and Name only', function () {
    $id = publicFormsMakeResource('Pest Listed Resource');

    $response = $this->getJson('/api/public/resources');

    $response->assertStatus(200);

    $row = collect($response->json())->firstWhere('id', $id);
    expect($row)->not->toBeNull()
        ->and(array_keys($row))->toEqualCanonicalizing(['id', 'Name']);
});

test('counties are listed as id and Name only', function () {
    $id = publicFormsMakeCounty('Pest Listed County');

    $response = $this->getJson('/api/public/counties');

    $response->assertStatus(200);

    $row = collect($response->json())->firstWhere('id', $id);
    expect($row)->not->toBeNull()
        ->and(array_keys($row))->toEqualCanonicalizing(['id', 'Name']);
});

test('the seeded lookup rows the contact form relies on are present', function () {
    expect(collect($this->getJson('/api/public/services')->json())->pluck('Name'))
        ->toContain('Emergency Shelter', 'Crisis Counseling', '24/7 Crisis Line');

    expect(collect($this->getJson('/api/public/resources')->json())->pluck('Name'))
        ->toContain('Safety Planning', 'Orders of Protection', 'Something Else');

    expect(collect($this->getJson('/api/public/counties')->json())->pluck('Name'))
        ->toContain('Bradley', 'Polk');
});
