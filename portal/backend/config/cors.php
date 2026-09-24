<?php

/*
Origins are env-driven rather than hardcoded so the real production/staging
domains never need to be checked into this file - set WEBSITE_URL/PORTAL_URL
once those domains exist and nothing here needs to change again. Local dev
origins are only added when APP_ENV is local, so a stray "localhost" entry
never ships to production.

Note for a subdomain setup (e.g. the API on api.example.com, the site on
example.com or www.example.com): CORS matches the browser's exact Origin
header - scheme + host + port - not just a port number, and a subdomain is a
different origin from its parent domain. So the site's own origin still has
to be listed explicitly (via WEBSITE_URL) even though it and the API share a
parent domain. WEBSITE_URL_PATTERN is there for the day multiple subdomains
(e.g. a preview-per-branch setup) need to match at once, via
allowed_origins_patterns - unused unless set.
*/
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([
        env('WEBSITE_URL'),
        env('PORTAL_URL'),
        // Local dev: 3000 is whichever frontend you started first (usually
        // website/frontend), 3001 the other one - both are allowed so the
        // website and the portal can run side by side against one API.
        ...(env('APP_ENV', 'production') === 'local' ? [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3001',
        ] : []),
    ])),
    'allowed_origins_patterns' => array_values(array_filter([
        env('WEBSITE_URL_PATTERN'),
    ])),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
