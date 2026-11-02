<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
Security headers on every response, the staff panel's and the API's (security
testing, 2026-11-02). Nothing here changes what a page does:

- No framing (X-Frame-Options, CSP frame-ancestors): another site can't load
  the panel in a hidden frame and trick staff into clicking. Nothing in the
  app frames its own pages either.
- nosniff: a file is only ever treated as the type it is sent as.
- Referrer-Policy same-origin: a panel address, which can hold a record's ID,
  is never passed to another site.
- CSP limited to what can't break Filament: no <base> or <object> tricks, and
  forms post only back to this site. It deliberately has no script-src:
  Filament and Livewire rely on inline scripts.
- HSTS only over https, so a browser that has seen the site keeps to https.
- Permissions-Policy: no page here uses the camera, microphone or location.

A header a controller has already set (the file routes' own nosniff) is left
as it is.
*/
class SecurityHeaders
{
    public const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'",
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // PHP adds "X-Powered-By: PHP/8.x" itself unless expose_php is off;
        // drop it here too, so the version isn't announced either way.
        header_remove('X-Powered-By');

        return $response;
    }
}
