<?php

use App\Http\Middleware\SecurityHeaders;

// Security testing (2026-11-02): the panel and the API send the usual
// security headers (App\Http\Middleware\SecurityHeaders).

test('the panel and the API send the security headers', function (string $path) {
    $response = $this->get($path);

    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
})->with([
    'sign-in page' => '/login',
    'public API' => '/api/public/services',
    'health check' => '/up',
]);

test('no site can show the panel in a frame', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
});

test('HSTS only over https', function () {
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
