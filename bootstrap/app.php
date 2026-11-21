<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\PerfilCompleto;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['sede.perfil' => PerfilCompleto::class]);
        $middleware->web(append: [CabecerasSeguridad::class]);
        // La notificación de Redsys llega de servidor a servidor: se valida por su firma HMAC.
        $middleware->preventRequestForgery(except: ['pagos/notificacion']);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('gestion', 'gestion/*') ? route('filament.gestion.auth.login') : route('sede.acceso'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
