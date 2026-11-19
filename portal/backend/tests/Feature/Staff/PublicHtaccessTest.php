<?php

/*
public/.htaccess on the IONOS server. Its first line, Require all granted,
and its RewriteBase / keep the live site reachable: without them IONOS refuses
every request or Laravel's routes stop resolving. The compression and caching
blocks appended after them are pinned here too, so an edit that drops them
shows up before the server does.
*/

function publicHtaccessLines(): array
{
    return preg_split('/\R/', file_get_contents(public_path('.htaccess')));
}

test('the first line is Require all granted', function () {
    expect(publicHtaccessLines()[0])->toBe('Require all granted');
});

test('it keeps RewriteBase /', function () {
    expect(array_map('trim', publicHtaccessLines()))->toContain('RewriteBase /');
});

test('CSS and JS are compressed, and only static files are cached', function () {
    $htaccess = file_get_contents(public_path('.htaccess'));

    expect($htaccess)
        ->toContain('<IfModule mod_deflate.c>')
        ->toMatch('/AddOutputFilterByType DEFLATE .*\btext\/css\b.*\bapplication\/javascript\b/')
        ->toContain('<IfModule mod_headers.c>')
        ->toContain('Header set Cache-Control "public, max-age=31536000, immutable"')
        ->toContain('Header set Cache-Control "public, max-age=2592000"');

    // HTML and JSON are never compressed (BREACH: pages carry CSRF tokens and
    // Livewire state).
    preg_match('/AddOutputFilterByType DEFLATE (.*)/', $htaccess, $deflate);

    expect($deflate[1])->not->toContain('text/html')->not->toContain('application/json');

    // Every Cache-Control is set inside a condition that needs a real file,
    // so nothing Laravel answers (HTML, PDFs, the API) is ever cached.
    preg_match_all('/<(?:If|ElseIf) "([^"]*)">\s*Header set Cache-Control/', $htaccess, $conditions);

    expect($conditions[1])->toHaveCount(substr_count($htaccess, 'Header set Cache-Control'));

    foreach ($conditions[1] as $condition) {
        expect($condition)->toContain('-f %{REQUEST_FILENAME}');
    }
});
