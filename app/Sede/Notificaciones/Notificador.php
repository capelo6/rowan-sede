<?php

namespace App\Sede\Notificaciones;

use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\Persona;
use App\Sede\Avisos\ServicioAvisos;
use App\Sede\Documentos\Almacen;
use App\Sede\Documentos\GeneradorPdf;
use DomainException;

/**
 * Notificaciones por comparecencia en sede (art. 43 Ley 39/2015): se ponen a disposición,
 * se avisa por correo y se entienden practicadas al acceder, o rechazadas a los 10 días naturales.
 */
class Notificador
{
    public function __construct(
        private readonly Almacen $almacen,
        private readonly GeneradorPdf $pdf,
        private readonly ServicioAvisos $avisos,
    ) {}

    public function poner(?Expediente $expediente, Persona $destinatario, string $asunto, Documento $documento, ?string $cuerpo = null): Notificacion
    {
        $notificacion = Notificacion::query()->create([
            'expediente_id' => $expediente?->id,
            'persona_id' => $destinatario->id,
            'asunto' => $asunto,
            'cuerpo' => $cuerpo,
            'documento_id' => $documento->id,
            'puesta_disposicion_at' => now(),
            'estado' => 'pendiente',
        ]);
        $this->avisos->notificacionPuesta($notificacion);

        return $notificacion;
    }

    /** Comparecencia: la persona accede al contenido. Genera el justificante de la notificación. */
    public function acceder(Notificacion $notificacion, Persona $persona): Notificacion
    {
        if ($notificacion->persona_id !== $persona->id) {
            throw new DomainException('Esta notificación no está dirigida a usted.');
        }
        if ($notificacion->estado !== 'pendiente') {
            return $notificacion;
        }
        $notificacion->update(['estado' => 'accedida', 'accedida_at' => now()]);
        $this->justificante($notificacion->refresh(), 'accedida');

        return $notificacion->refresh();
    }

    /** Tarea programada: rechaza las notificaciones no abiertas en el plazo. */
    public function rechazarVencidas(): int
    {
        $limite = now()->subDays((int) config('sede.notificaciones.dias_rechazo', 10));
        $n = 0;
        foreach (Notificacion::query()->where('estado', 'pendiente')->where('puesta_disposicion_at', '<=', $limite)->get() as $notificacion) {
            $notificacion->update(['estado' => 'rechazada', 'rechazada_at' => now()]);
            $this->justificante($notificacion->refresh(), 'rechazada');
            $n++;
        }

        return $n;
    }

    private function justificante(Notificacion $notificacion, string $resultado): void
    {
        $csv = $this->almacen->nuevoCsv();
        $doc = $notificacion->documento;
        $filas = '<tr><td style="width:40%;"><strong>Asunto</strong></td><td style="width:60%;">'.e($notificacion->asunto).'</td></tr>'
            .'<tr><td><strong>Destinatario</strong></td><td>'.e($notificacion->persona->nombreCompleto()).' · '.e($notificacion->persona->nif).'</td></tr>'
            .($notificacion->expediente ? '<tr><td><strong>Expediente</strong></td><td>'.e((string) $notificacion->expediente->numero).'</td></tr>' : '')
            .'<tr><td><strong>Puesta a disposición</strong></td><td>'.$notificacion->puesta_disposicion_at->format('d/m/Y H:i:s').'</td></tr>'
            .($resultado === 'accedida'
                ? '<tr><td><strong>Acceso al contenido</strong></td><td>'.$notificacion->accedida_at?->format('d/m/Y H:i:s').'</td></tr>'
                : '<tr><td><strong>Rechazo</strong></td><td>'.$notificacion->rechazada_at?->format('d/m/Y H:i:s').' (transcurridos '.(int) config('sede.notificaciones.dias_rechazo', 10).' días naturales sin acceder)</td></tr>')
            .($doc ? '<tr><td><strong>Documento notificado</strong></td><td>'.e($doc->nombre).'<br/><span style="font-size:7pt;">SHA-256 '.e($doc->sha256).'</span></td></tr>' : '');
        $html = '<table cellpadding="3" border="1" style="border-color:#999999;">'.$filas.'</table>'
            .'<p>'.($resultado === 'accedida'
                ? 'La notificación se entiende practicada en el momento del acceso a su contenido (art. 43.2 de la Ley 39/2015).'
                : 'La notificación se entiende rechazada y se da por efectuado el trámite (art. 43.2 de la Ley 39/2015).').'</p>';

        $contenido = $this->pdf->generar('Justificante de notificación', $html, $csv);
        $justificante = $this->almacen->guardarContenido($contenido, 'pdf', $notificacion->expediente, 'justificante_notificacion', [
            'nombre' => 'Justificante de notificación - '.$notificacion->asunto.'.pdf',
            'csv' => $csv,
            'origen' => 'organismo',
            'persona_id' => $notificacion->persona_id,
            'firmado' => $this->pdf->estaSellando(),
            'metadatos' => ['notificacion_id' => $notificacion->id, 'resultado' => $resultado],
        ]);
        $notificacion->update(['justificante_id' => $justificante->id]);
    }
}
