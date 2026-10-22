<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
The JSON API, written out in full (team plan task 29). It is only the narrow
public surface the website uses: no signed-in routes, because staff work in
the Filament panel and nothing issues API tokens. The per-table routes that
sat behind auth:sanctum - assessments, private assessments, submitter info,
officer assessments, agents, agencies and GET /api/user - were removed.

A new /api route fails here until it is added to this list on purpose, so
nobody brings back an unreachable or unscoped one by accident.
*/

function apiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => $route->uri() === 'api' || str_starts_with($route->uri(), 'api/'))
        ->map(fn (RouteDefinition $route): string => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri())
        ->sort()
        ->values()
        ->all();
}

test('the API is exactly the public surface', function () {
    expect(apiRoutes())->toBe([
        'GET api',
        'GET api/public/content-files/{file}',
        'GET api/public/counties',
        'GET api/public/events',
        'GET api/public/newsletters',
        'GET api/public/relationships',
        'GET api/public/resources',
        'GET api/public/services',
        'POST api/public/civilian-assessments',
        'POST api/public/resource-requests',
        'POST api/public/service-feedback',
    ]);
});

test('no API route needs a signed-in user, and every public write is throttled', function () {
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api')) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        expect(collect($middleware)->contains(fn (string $m): bool => str_starts_with($m, 'auth')))
            ->toBeFalse("{$route->uri()} must not need a signed-in user");

        if (in_array('POST', $route->methods(), true)) {
            expect($middleware)->toContain('throttle:10,1');
        }
    }
});

test('the old per-table routes are gone', function (string $method, string $url) {
    $this->json($method, $url)->assertNotFound();
})->with([
    ['GET', '/api/user'],
    ['POST', '/api/assessments'],
    ['POST', '/api/private-assessments'],
    ['POST', '/api/submitter-info'],
    ['GET', '/api/law-enforcement-assessments'],
    ['POST', '/api/law-enforcement-assessments'],
    ['PUT', '/api/law-enforcement-assessments/1'],
    ['GET', '/api/law-enforcement-agents'],
    ['GET', '/api/agencies'],
]);
