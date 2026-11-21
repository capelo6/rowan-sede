<?php

namespace App\Http\Controllers\Sede;

use App\Models\Expediente;
use App\Models\Pago;
use App\Sede\Pagos\Redsys;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PagoController extends Controlador
{
    public function __construct(private readonly Redsys $redsys) {}

    public function iniciar(Request $request, Expediente $expediente): View|RedirectResponse
    {
        $this->comprobarExpediente($expediente);
        $request->validate(['canal' => ['required', 'in:tarjeta,bizum']]);
        $liquidacion = $expediente->liquidacionPendiente();
        abort_if($liquidacion === null, 404);
        try {
            $formulario = $this->redsys->iniciar($liquidacion, (string) $request->input('canal'));
        } catch (DomainException $e) {
            return back()->withErrors(['pago' => $e->getMessage()]);
        }

        return view('sede.pagos.redireccion', $formulario + ['liquidacion' => $liquidacion]);
    }

    /** Vuelta del navegador desde la pasarela. El resultado definitivo llega por la notificación en línea. */
    public function vuelta(Pago $pago, Request $request): View
    {
        $expediente = $pago->liquidacion->expediente;
        $this->comprobarExpediente($expediente);

        return view('sede.pagos.vuelta', ['pago' => $pago->refresh(), 'expediente' => $expediente, 'resultado' => $request->query('resultado')]);
    }

    public function notificacion(Request $request): Response
    {
        $pago = $this->redsys->procesarNotificacion($request->all());
        if ($pago === null) {
            Log::warning('Notificación de Redsys rechazada (firma o pedido no válidos).');

            return response('KO', 400);
        }

        return response('OK');
    }
}
