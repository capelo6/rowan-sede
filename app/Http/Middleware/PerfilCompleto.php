<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** En el primer acceso hay que completar el correo, aceptar la privacidad y decidir sobre las notificaciones. */
class PerfilCompleto
{
    public function handle(Request $request, Closure $next): Response
    {
        $persona = $request->user('sede');
        if ($persona && ! $persona->perfilCompleto()) {
            return redirect()->route('sede.perfil');
        }

        return $next($request);
    }
}
