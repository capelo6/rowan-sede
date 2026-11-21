<?php

namespace App\Sede\Pagos;

use App\Enums\EstadoExpediente;
use App\Models\Expediente;
use App\Models\Liquidacion;
use App\Models\LiquidacionLinea;
use App\Models\Pago;
use App\Models\Tasa;
use App\Models\User;
use App\Sede\Avisos\ServicioAvisos;
use App\Sede\Documentos\Almacen;
use App\Sede\Documentos\GeneradorPdf;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Plazos\Calendario;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Asignación de tasas a un expediente y registro de los pagos. */
class Liquidador
{
    public function __construct(
        private readonly Tramitador $tramitador,
        private readonly Calendario $calendario,
        private readonly Almacen $almacen,
        private readonly GeneradorPdf $pdf,
        private readonly ServicioAvisos $avisos,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas  Del catálogo: {tasa_id, cantidad}. Manuales: {concepto, importe, iva_porcentaje, motivo}.
     */
    public function emitir(Expediente $expediente, User $usuario, array $lineas, ?int $plazoDias = null): Liquidacion
    {
        if ($lineas === []) {
            throw new DomainException('Añada al menos una tasa.');
        }
        if ($expediente->liquidacionPendiente()) {
            throw new DomainException('El expediente ya tiene una liquidación pendiente.');
        }

        $liquidacion = DB::transaction(function () use ($expediente, $usuario, $lineas, $plazoDias) {
            $liq = Liquidacion::query()->create([
                'expediente_id' => $expediente->id,
                'referencia' => 'TMP-'.uniqid(),
                'estado' => 'pendiente',
                'emitida_por' => $usuario->id,
                'vence_at' => $this->calendario->venceHabiles(now(), $plazoDias ?? (int) config('sede.plazos.pago_dias', 10)),
            ]);
            $liq->update(['referencia' => sprintf('L%d-%06d', (int) now()->format('Y'), $liq->id)]);

            foreach ($lineas as $l) {
                if (! empty($l['tasa_id'])) {
                    $tasa = Tasa::query()->findOrFail($l['tasa_id']);
                    $cantidad = (float) ($l['cantidad'] ?? 1);
                    $calc = LiquidacionLinea::calcular($cantidad, (float) $tasa->importe, (float) $tasa->iva_porcentaje);
                    $liq->lineas()->create(array_merge($calc, [
                        'tasa_id' => $tasa->id,
                        'concepto' => $tasa->concepto,
                        'cantidad' => $cantidad,
                        'importe_unitario' => $tasa->importe,
                        'iva_porcentaje' => $tasa->iva_porcentaje,
                    ]));
                } else {
                    if (trim((string) ($l['motivo'] ?? '')) === '') {
                        throw new DomainException('Las líneas manuales tienen que estar motivadas.');
                    }
                    $calc = LiquidacionLinea::calcular(1, (float) $l['importe'], (float) ($l['iva_porcentaje'] ?? 0));
                    $liq->lineas()->create(array_merge($calc, [
                        'concepto' => (string) $l['concepto'],
                        'cantidad' => 1,
                        'importe_unitario' => (float) $l['importe'],
                        'iva_porcentaje' => (float) ($l['iva_porcentaje'] ?? 0),
                        'motivo' => (string) $l['motivo'],
                    ]));
                }
            }
            $liq->recalcular();

            return $liq;
        });

        $this->tramitador->cambiarEstado($expediente, EstadoExpediente::PendientePago,
            'Tasas asignadas: '.number_format((float) $liquidacion->total, 2, ',', '.').' €.', 'usuario', $usuario->id);
        $this->avisos->tasa($liquidacion);

        return $liquidacion;
    }

    /** Registra un pago autorizado (pasarela o ventanilla) y genera el justificante. */
    public function registrarPago(Liquidacion $liquidacion, string $canal, ?User $usuario = null, ?Pago $pago = null, array $datosPasarela = [], ?string $autorizacion = null): Pago
    {
        if ($liquidacion->estado === 'pagada') {
            return $pago ?? $liquidacion->pagos()->where('estado', 'autorizado')->firstOrFail();
        }
        if ($liquidacion->estado !== 'pendiente') {
            throw new DomainException('La liquidación no está pendiente de pago.');
        }

        $pago ??= new Pago(['liquidacion_id' => $liquidacion->id, 'canal' => $canal, 'importe' => $liquidacion->total]);
        $pago->fill([
            'canal' => $canal,
            'estado' => 'autorizado',
            'autorizacion' => $autorizacion,
            'datos_pasarela' => $datosPasarela ?: $pago->datos_pasarela,
            'registrado_por' => $usuario?->id,
            'pagado_at' => now(),
        ])->save();

        $liquidacion->update(['estado' => 'pagada', 'pagada_at' => now()]);

        $expediente = $liquidacion->expediente;
        $csv = $this->almacen->nuevoCsv();
        $filas = '';
        foreach ($liquidacion->lineas as $l) {
            $filas .= '<tr><td>'.e($l->concepto).'</td><td style="text-align:right;">'.number_format((float) $l->base, 2, ',', '.')
                .'</td><td style="text-align:right;">'.number_format((float) $l->iva, 2, ',', '.').'</td><td style="text-align:right;">'
                .number_format((float) $l->total, 2, ',', '.').'</td></tr>';
        }
        $html = '<table cellpadding="3" border="1" style="border-color:#999999;">'
            .'<tr><td style="width:35%;"><strong>Expediente</strong></td><td style="width:65%;">'.e((string) $expediente->numero).'</td></tr>'
            .'<tr><td><strong>Liquidación</strong></td><td>'.e($liquidacion->referencia).'</td></tr>'
            .'<tr><td><strong>Fecha y hora del pago</strong></td><td>'.$pago->pagado_at->format('d/m/Y H:i:s').'</td></tr>'
            .'<tr><td><strong>Medio</strong></td><td>'.e(Pago::CANALES[$canal] ?? $canal).($autorizacion ? ' · autorización '.e($autorizacion) : '').'</td></tr>'
            .'<tr><td><strong>Interesado</strong></td><td>'.e($expediente->titular->nombreCompleto()).' · '.e($expediente->titular->nif).'</td></tr>'
            .'</table><p></p><table cellpadding="3" border="1" style="border-color:#999999;"><tr><th style="width:52%;">Concepto</th><th style="width:16%;">Base</th><th style="width:16%;">IVA</th><th style="width:16%;">Total</th></tr>'
            .$filas.'<tr><td><strong>Total pagado</strong></td><td></td><td></td><td style="text-align:right;"><strong>'.number_format((float) $liquidacion->total, 2, ',', '.').' €</strong></td></tr></table>';
        $contenido = $this->pdf->generar('Justificante de pago', $html, $csv);
        $justificante = $this->almacen->guardarContenido($contenido, 'pdf', $expediente, 'justificante_pago', [
            'nombre' => 'Justificante de pago '.$liquidacion->referencia.'.pdf',
            'csv' => $csv,
            'origen' => 'organismo',
            'persona_id' => $expediente->presentador_id,
            'firmado' => $this->pdf->estaSellando(),
            'metadatos' => ['pago_id' => $pago->id],
        ]);
        $pago->update(['justificante_id' => $justificante->id]);

        if ($expediente->estado === EstadoExpediente::PendientePago) {
            $expediente->update(['desistimiento_propuesto_at' => null]);
            $this->tramitador->cambiarEstado($expediente, EstadoExpediente::Pagado,
                'Pago registrado ('.(Pago::CANALES[$canal] ?? $canal).').', $usuario ? 'usuario' : 'sistema', $usuario?->id);
        }
        $this->avisos->pagoRecibido($pago);

        return $pago->refresh();
    }
}
