<?php

/*
The panel's browser-tab icon, phone home-screen icon and web app manifest
(StaffPanelProvider's ->favicon() and its HEAD_END hook,
filament/partials/app-icons.blade.php). Sign-in is the first page anyone
sees, and the one a phone saves to its home screen, so it must carry them.
*/

test('the sign-in page links the favicon, home-screen icon and manifest', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.asset('images/favicon-32.png').'">', false)
        ->assertSee(asset('favicon.ico'), false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="'.asset('images/apple-touch-icon.png').'">', false)
        ->assertSee('<link rel="manifest" href="'.asset('manifest.webmanifest').'">', false)
        ->assertSee('<meta name="theme-color" content="#5d2c87">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="Harbor Safe">', false);
});

test('every file the sign-in page and the manifest point at exists in public/', function () {
    foreach (['favicon.ico', 'images/favicon-32.png', 'images/apple-touch-icon.png', 'manifest.webmanifest'] as $file) {
        expect(public_path($file))->toBeFile();
    }

    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('Harbor Safe House Portal')
        ->short_name->toBe('Harbor Safe')
        ->start_url->toBe('/')
        ->display->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(3);

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

test('the server sends the manifest as JSON', function () {
    expect(file_get_contents(public_path('.htaccess')))
        ->toMatch('/^AddType application\/manifest\+json \.webmanifest$/m');
});
