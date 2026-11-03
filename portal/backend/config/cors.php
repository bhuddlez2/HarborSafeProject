<?php

/*
Origins are env-driven rather than hardcoded so the real production/staging
domains never need to be checked into this file - set WEBSITE_URL once that
domain exists and nothing here needs to change again. Local dev
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

Only the website calls this API from a browser on another origin: the staff
panel is served by this backend itself (same origin, no CORS involved). So
only the website's origin, and only what its pages actually send - GET and
POST with a JSON body - are allowed. CORS is enforced by browsers, not by the
server, so this narrows what other sites' pages can do in a visitor's
browser; the real protection is still the throttles, validation and
restricted database users behind each route.
*/
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => array_values(array_filter([
        env('WEBSITE_URL'),
        // Local dev: website/frontend's `npm run dev` serves on 3000.
        ...(env('APP_ENV', 'production') === 'local' ? [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ] : []),
    ])),
    'allowed_origins_patterns' => array_values(array_filter([
        env('WEBSITE_URL_PATTERN'),
    ])),
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
