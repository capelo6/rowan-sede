<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad. En la sede pública se aplica una CSP estricta (sin scripts en línea);
 * se permite la conexión local con AutoFirma y el envío del formulario a la pasarela de pago.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $request->is('gestion', 'gestion/*', 'livewire*', 'filament*')) {
            $pasarela = collect(config('sede.pagos.redsys.url', []))
                ->map(fn ($u) => parse_url((string) $u, PHP_URL_SCHEME).'://'.parse_url((string) $u, PHP_URL_HOST).(parse_url((string) $u, PHP_URL_PORT) ? ':'.parse_url((string) $u, PHP_URL_PORT) : ''))
                ->unique()->implode(' ');
            $respuesta->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                // autoscript.js (AutoFirma) inserta un <style> para su diálogo de ayuda.
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self'",
                // AutoFirma se arranca con un iframe afirma:// y escucha en 127.0.0.1 (WebSocket o HTTP).
                "frame-src afirma:",
                "connect-src 'self' ws://127.0.0.1:* wss://127.0.0.1:* http://127.0.0.1:* https://127.0.0.1:*",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self' ".$pasarela,
                "object-src 'none'",
            ]));
        }

        return $respuesta;
    }
}
