<?php

namespace App\Sede\Avisos;

use App\Enums\EstadoExpediente;
use App\Mail\AvisoSede;
use App\Models\Actuacion;
use App\Models\Aviso;
use App\Models\Expediente;
use App\Models\Liquidacion;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Registro;
use App\Models\Requerimiento;
use App\Sede\Plazos\Calendario;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisos por correo a la persona interesada. Cada aviso tiene una clave única,
 * así que nunca se envía dos veces el mismo, aunque la tarea programada se repita.
 */
class ServicioAvisos
{
    public function __construct(private readonly Calendario $calendario) {}

    public function solicitudPresentada(Expediente $e, Registro $registro): void
    {
        $this->enviar($e->presentador, 'presentada:'.$e->id, 'solicitud_presentada', $e,
            'Hemos recibido su solicitud '.$e->numero,
            ['Hemos registrado su solicitud de «'.$e->procedimiento->nombre.'».',
                'Número de expediente: '.$e->numero.'. Registro de entrada: '.$registro->codigo().', '.$registro->registrado_at->format('d/m/Y H:i').'.',
                'Tiene el justificante de registro en su carpeta electrónica.'],
            route('sede.expedientes.ver', $e));
    }

    public function cambioEstado(Expediente $e, EstadoExpediente $de, EstadoExpediente $a): void
    {
        $this->enviar($e->presentador, 'estado:'.$e->id.':'.$a->value.':'.now()->timestamp, 'cambio_estado', $e,
            'Su expediente '.$e->numero.' está «'.$a->etiqueta().'»',
            ['Su expediente '.$e->numero.' ('.$e->procedimiento->nombre.') ha pasado de «'.$de->etiqueta().'» a «'.$a->etiqueta().'».', $a->explicacion()],
            route('sede.expedientes.ver', $e));
    }

    public function notificacionPuesta(Notificacion $n): void
    {
        $this->avisoNotificacion($n, 0);
    }

    public function requerimiento(Requerimiento $r): void
    {
        $this->avisoRequerimiento($r, '0');
    }

    public function tasa(Liquidacion $l): void
    {
        $this->avisoTasa($l, '0');
    }

    public function pagoRecibido(Pago $p): void
    {
        $e = $p->liquidacion->expediente;
        $this->enviar($e->presentador, 'pago:'.$p->id, 'pago_recibido', $e,
            'Hemos recibido su pago',
            ['Hemos registrado el pago de '.number_format((float) $p->importe, 2, ',', '.').' € del expediente '.$e->numero.'.',
                'Tiene el justificante de pago en su carpeta electrónica.'],
            route('sede.expedientes.ver', $e));
    }

    public function actuacionRealizada(Actuacion $a): void
    {
        $e = $a->expediente;
        $this->enviar($e->presentador, 'actuacion:'.$a->id, 'actuacion_realizada', $e,
            'Servicio realizado: '.$a->descripcion,
            ['Le informamos de que se ha realizado el servicio «'.$a->descripcion.'» del expediente '.$e->numero.'.',
                $a->evidencia_id ? 'Puede ver la fotografía en su carpeta electrónica.' : ''],
            route('sede.expedientes.ver', $e), $a->evidencia);
    }

    /** Tarea programada: recordatorios a mitad de plazo y antes del vencimiento. */
    public function programados(): int
    {
        $enviados = 0;
        $hoy = now()->startOfDay();

        foreach (Notificacion::query()->where('estado', 'pendiente')->with(['persona', 'expediente.procedimiento'])->get() as $n) {
            $dias = (int) $n->puesta_disposicion_at->copy()->startOfDay()->diffInDays($hoy, true);
            foreach (config('sede.avisos.notificacion_sin_abrir', [0, 5, 8]) as $regla) {
                if (is_int($regla) && $regla > 0 && $dias >= $regla) {
                    $enviados += (int) $this->avisoNotificacion($n, $regla);
                }
            }
        }

        foreach (Requerimiento::query()->where('estado', 'pendiente')->whereNotNull('vence_at')->with('expediente.presentador')->get() as $r) {
            foreach ($this->fechasRegla(config('sede.avisos.requerimiento', []), $r->notificado_at ?? $r->created_at, $r->vence_at) as $clave => $fecha) {
                if ($clave !== '0' && $hoy->gte($fecha)) {
                    $enviados += (int) $this->avisoRequerimiento($r, $clave);
                }
            }
        }

        foreach (Liquidacion::query()->where('estado', 'pendiente')->whereNotNull('vence_at')->with('expediente.presentador')->get() as $l) {
            foreach ($this->fechasRegla(config('sede.avisos.tasa', []), $l->created_at, $l->vence_at) as $clave => $fecha) {
                if ($clave !== '0' && $hoy->gte($fecha)) {
                    $enviados += (int) $this->avisoTasa($l, $clave);
                }
            }
        }

        // Renovación de servicios periódicos: un mes antes.
        foreach (Actuacion::query()->whereNotNull('proxima_renovacion')->whereDate('proxima_renovacion', '<=', $hoy->copy()->addMonth())->with('expediente.presentador')->get() as $a) {
            $e = $a->expediente;
            $enviados += (int) $this->enviar($e->presentador, 'renovacion:'.$a->id.':'.$a->proxima_renovacion->toDateString(), 'renovacion', $e,
                'Renovación del servicio: '.$a->descripcion,
                ['El servicio «'.$a->descripcion.'» del expediente '.$e->numero.' vence el '.$a->proxima_renovacion->format('d/m/Y').'.',
                    'Si desea mantenerlo, puede solicitar la renovación desde la sede electrónica.'],
                route('sede.expedientes.ver', $e));
        }

        return $enviados;
    }

    private function avisoNotificacion(Notificacion $n, int $dia): bool
    {
        $texto = $dia === 0
            ? ['Tiene una notificación a su disposición en la sede electrónica: «'.$n->asunto.'».']
            : ['Recuerde que tiene una notificación sin abrir desde el '.$n->puesta_disposicion_at->format('d/m/Y').': «'.$n->asunto.'».',
                'Si no accede a ella antes del '.$n->venceAt()->format('d/m/Y').', se entenderá rechazada (art. 43.2 de la Ley 39/2015).'];

        return $this->enviar($n->persona, 'notif:'.$n->id.':'.$dia, 'notificacion', $n->expediente,
            $dia === 0 ? 'Tiene una notificación en la sede electrónica' : 'Recordatorio: notificación sin abrir',
            array_merge($texto, ['Por seguridad, este correo no incluye el contenido. Acceda con su certificado electrónico.']),
            route('sede.notificaciones.ver', $n));
    }

    private function avisoRequerimiento(Requerimiento $r, string $clave): bool
    {
        $e = $r->expediente;

        return $this->enviar($e->presentador, 'req:'.$r->id.':'.$clave, 'requerimiento', $e,
            $clave === '0' ? 'Necesitamos documentación del expediente '.$e->numero : 'Recordatorio: documentación pendiente del expediente '.$e->numero,
            ['Para continuar con su expediente '.$e->numero.' necesitamos que aporte la documentación o los datos requeridos.',
                $r->vence_at ? 'Plazo: hasta el '.$r->vence_at->format('d/m/Y').' (quedan '.max(0, $this->calendario->habilesRestantes($r->vence_at)).' días hábiles).' : ''],
            route('sede.expedientes.ver', $e));
    }

    private function avisoTasa(Liquidacion $l, string $clave): bool
    {
        $e = $l->expediente;

        return $this->enviar($e->presentador, 'tasa:'.$l->id.':'.$clave, 'tasa', $e,
            $clave === '0' ? 'Tasa pendiente de pago del expediente '.$e->numero : 'Recordatorio: tasa pendiente del expediente '.$e->numero,
            ['El expediente '.$e->numero.' tiene una tasa pendiente de '.number_format((float) $l->total, 2, ',', '.').' €.',
                $l->vence_at ? 'Puede pagarla con tarjeta o Bizum hasta el '.$l->vence_at->format('d/m/Y').'.' : ''],
            route('sede.expedientes.ver', $e));
    }

    /** @return array<string, \Carbon\CarbonInterface> */
    private function fechasRegla(array $reglas, $inicio, $vence): array
    {
        $fechas = [];
        foreach ($reglas as $regla) {
            if ($regla === 'mitad') {
                $fechas['mitad'] = $this->calendario->mitad($inicio, $vence);
            } elseif (is_int($regla) && $regla < 0) {
                $fechas['antes'.abs($regla)] = \Carbon\CarbonImmutable::parse($vence)->addDays($regla);
            } elseif ($regla === 0) {
                $fechas['0'] = \Carbon\CarbonImmutable::parse($inicio);
            }
        }

        return $fechas;
    }

    /** @param list<string> $lineas */
    private function enviar(?Persona $persona, string $clave, string $tipo, ?Expediente $expediente, string $asunto, array $lineas, ?string $enlace = null, $adjunto = null): bool
    {
        if (! $persona || ! $persona->email || Aviso::query()->where('clave', $clave)->exists()) {
            return false;
        }
        $aviso = Aviso::query()->create([
            'clave' => $clave,
            'persona_id' => $persona->id,
            'expediente_id' => $expediente?->id,
            'tipo' => $tipo,
            'asunto' => $asunto,
        ]);
        try {
            Mail::to($persona->email)->send(new AvisoSede($asunto, array_values(array_filter($lineas)), $enlace, $adjunto));
            $aviso->update(['enviado_at' => now()]);
        } catch (Throwable $ex) {
            $aviso->update(['error' => mb_substr($ex->getMessage(), 0, 1000)]);
        }

        return true;
    }
}
