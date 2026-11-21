<?php

namespace App\Sede\Expedientes;

use App\Enums\EstadoExpediente;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ExpedienteEstado;
use App\Models\Persona;
use App\Models\Plantilla;
use App\Models\Procedimiento;
use App\Models\Registro;
use App\Models\Representacion;
use App\Models\Requerimiento;
use App\Models\User;
use App\Sede\Avisos\ServicioAvisos;
use App\Sede\Documentos\Almacen;
use App\Sede\Documentos\GeneradorPdf;
use App\Sede\Notificaciones\Notificador;
use App\Sede\Organismo;
use App\Sede\Plazos\Calendario;
use App\Sede\Registro\LibroRegistro;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Reglas de tramitación del expediente: estados, registro, requerimientos, desistimiento y resolución. */
class Tramitador
{
    public function __construct(
        private readonly LibroRegistro $libro,
        private readonly Almacen $almacen,
        private readonly GeneradorPdf $pdf,
        private readonly Calendario $calendario,
        private readonly Notificador $notificador,
        private readonly ServicioAvisos $avisos,
    ) {}

    public function crearBorrador(Procedimiento $procedimiento, Persona $presentador, ?Representacion $representacion = null): Expediente
    {
        if ($representacion && ! $procedimiento->permite_representacion) {
            throw new DomainException('Este procedimiento no admite representación.');
        }

        return Expediente::query()->create([
            'procedimiento_id' => $procedimiento->id,
            'titular_id' => $representacion?->representado_id ?? $presentador->id,
            'presentador_id' => $presentador->id,
            'representacion_id' => $representacion?->id,
            'estado' => EstadoExpediente::Borrador,
            'datos' => [],
        ]);
    }

    /**
     * Presenta la solicitud firmada: numera el expediente, asienta el registro de entrada
     * y genera el justificante.
     *
     * @param  list<Documento>  $documentos  Solicitud, firma y documentos aportados.
     */
    public function presentar(Expediente $expediente, array $documentos): Registro
    {
        if ($expediente->estado !== EstadoExpediente::Borrador) {
            throw new DomainException('La solicitud ya se presentó.');
        }

        DB::transaction(function () use ($expediente) {
            $ejercicio = (int) now()->format('Y');
            $prefijo = $ejercicio.'/';
            $ultimo = Expediente::query()->where('numero', 'like', $prefijo.'%')->lockForUpdate()->pluck('numero')
                ->map(fn ($n) => (int) substr((string) $n, strlen($prefijo)))->max() ?? 0;
            $expediente->numero = sprintf((string) config('sede.expedientes.formato_numero', '%d/%06d'), $ejercicio, $ultimo + 1);
            $expediente->presentado_at = now();
            $expediente->save();
        });

        $registro = $this->libro->asentar('entrada', 'Solicitud: '.$expediente->procedimiento->nombre, $expediente, $expediente->presentador,
            null, $documentos);
        $this->cambiarEstado($expediente, EstadoExpediente::Nueva, 'Solicitud presentada y firmada. Registro '.$registro->codigo().'.', 'persona', $expediente->presentador_id);
        $this->avisos->solicitudPresentada($expediente, $registro);

        return $registro;
    }

    public function cambiarEstado(Expediente $expediente, EstadoExpediente $destino, ?string $motivo, string $actorTipo, ?int $actorId = null): void
    {
        $origen = $expediente->estado;
        if ($origen === $destino) {
            return;
        }
        if (! $origen->puedePasarA($destino)) {
            throw new DomainException(sprintf('No se puede pasar de «%s» a «%s».', $origen->etiqueta(), $destino->etiqueta()));
        }

        $expediente->estado = $destino;
        $expediente->estado_cambiado_at = now();
        $expediente->save();

        ExpedienteEstado::query()->create([
            'expediente_id' => $expediente->id,
            'estado_anterior' => $origen,
            'estado_nuevo' => $destino,
            'motivo' => $motivo,
            'actor_tipo' => $actorTipo,
            'actor_id' => $actorId,
        ]);

        if ($origen !== EstadoExpediente::Borrador) {
            $this->avisos->cambioEstado($expediente, $origen, $destino);
        }
    }

    /** «Revisar solicitud»: asigna el expediente a quien lo revisa. */
    public function asignar(Expediente $expediente, User $usuario): void
    {
        $expediente->asignado_a = $usuario->id;
        $expediente->save();
        if ($expediente->estado === EstadoExpediente::Nueva) {
            $this->cambiarEstado($expediente, EstadoExpediente::EnRevision, 'Revisión asignada a '.$usuario->name.'.', 'usuario', $usuario->id);
        }
    }

    /**
     * Requerimiento de subsanación: la persona interesada verá solo lo requerido.
     *
     * @param  list<array{clave: string, tipo: string, descripcion: string}>  $items
     */
    public function requerir(Expediente $expediente, User $usuario, array $items, ?string $motivo = null, ?int $plazoDias = null): Requerimiento
    {
        if ($items === []) {
            throw new DomainException('Indique al menos un documento o dato que subsanar.');
        }
        $plazoDias ??= (int) config('sede.plazos.subsanacion_dias', 10);

        $requerimiento = Requerimiento::query()->create([
            'expediente_id' => $expediente->id,
            'user_id' => $usuario->id,
            'motivo' => $motivo,
            'items' => array_values($items),
            'plazo_dias' => $plazoDias,
            'plazo_habiles' => true,
            'estado' => 'pendiente',
        ]);

        $lista = '';
        foreach ($items as $item) {
            $lista .= '<li>'.e($item['descripcion']).'</li>';
        }
        $html = '<p>En relación con el expediente <strong>'.e((string) $expediente->numero).'</strong> ('.e($expediente->procedimiento->nombre).'), '
            .'se le requiere para que, en el plazo de <strong>'.$plazoDias.' días hábiles</strong> desde el día siguiente a la recepción de esta notificación, '
            .'aporte lo siguiente:</p><ul>'.$lista.'</ul>'
            .($motivo ? '<p>'.nl2br(e($motivo)).'</p>' : '')
            .'<p>Si no lo hace, se le tendrá por desistido de su petición, de acuerdo con el artículo 68 de la Ley 39/2015, '
            .'del Procedimiento Administrativo Común de las Administraciones Públicas.</p>';

        $documento = $this->documentoSalida('Requerimiento de subsanación', $html, $expediente, 'requerimiento', $usuario);
        $this->libro->asentar('salida', 'Requerimiento de subsanación', $expediente, $expediente->titular, null, [$documento]);
        $notificacion = $this->notificador->poner($expediente, $expediente->presentador, 'Requerimiento de subsanación', $documento);

        // El plazo cuenta desde la notificación; mientras tanto se calcula de forma provisional desde hoy.
        $requerimiento->update([
            'notificado_at' => $notificacion->puesta_disposicion_at,
            'vence_at' => $this->calendario->venceHabiles($notificacion->puesta_disposicion_at, $plazoDias),
        ]);

        $this->cambiarEstado($expediente, EstadoExpediente::EsperandoDocumentacion, $motivo ?: 'Requerimiento de subsanación.', 'usuario', $usuario->id);
        $this->avisos->requerimiento($requerimiento->refresh());

        return $requerimiento;
    }

    /**
     * La persona interesada aporta lo requerido: registro de entrada y vuelta a revisión,
     * aunque el sistema ya hubiera propuesto el desistimiento (mientras no se confirme).
     *
     * @param  list<Documento>  $documentos
     * @param  array<string, string>  $datos
     */
    public function atenderRequerimiento(Requerimiento $requerimiento, Persona $persona, array $documentos, array $datos = []): Registro
    {
        $requerimiento->refresh();
        $expediente = $requerimiento->expediente()->firstOrFail(); // siempre el estado actual, no una copia en caché
        if ($requerimiento->estado !== 'pendiente' || $expediente->estado->esFinal()) {
            throw new DomainException('Este requerimiento ya no admite respuesta.');
        }

        if ($datos !== []) {
            $expediente->datos = array_merge($expediente->datos ?? [], $datos);
            $expediente->save();
        }

        $registro = $this->libro->asentar('entrada', 'Subsanación del requerimiento', $expediente, $persona, null, $documentos);
        $requerimiento->update(['estado' => 'atendido', 'atendido_at' => now()]);
        $expediente->update(['desistimiento_propuesto_at' => null]);
        $this->cambiarEstado($expediente, EstadoExpediente::EnRevision, 'Documentación aportada. Registro '.$registro->codigo().'.', 'persona', $persona->id);

        return $registro;
    }

    /** Tarea programada: propone el desistimiento de los expedientes con el plazo de subsanación o de pago vencido. */
    public function proponerDesistimientos(): int
    {
        $n = 0;
        $hoy = now()->startOfDay();
        $vencidos = Requerimiento::query()->where('estado', 'pendiente')->whereDate('vence_at', '<', $hoy)->with('expediente')->get();
        foreach ($vencidos as $req) {
            if ($req->expediente->desistimiento_propuesto_at === null && ! $req->expediente->estado->esFinal()) {
                $req->expediente->update(['desistimiento_propuesto_at' => now()]);
                $n++;
            }
        }
        $impagados = \App\Models\Liquidacion::query()->where('estado', 'pendiente')->whereDate('vence_at', '<', $hoy)->with('expediente')->get();
        foreach ($impagados as $liq) {
            if ($liq->expediente->desistimiento_propuesto_at === null && ! $liq->expediente->estado->esFinal()) {
                $liq->expediente->update(['desistimiento_propuesto_at' => now()]);
                $n++;
            }
        }

        return $n;
    }

    public function confirmarDesistimiento(Expediente $expediente, User $usuario): void
    {
        if ($expediente->desistimiento_propuesto_at === null) {
            throw new DomainException('El sistema no ha propuesto el desistimiento de este expediente.');
        }
        $html = '<p>Transcurrido el plazo sin que se haya atendido lo requerido, se le tiene por desistido de su solicitud del expediente <strong>'
            .e((string) $expediente->numero).'</strong> ('.e($expediente->procedimiento->nombre).') y se archiva, de acuerdo con el artículo 68 de la Ley 39/2015.</p>';
        $documento = $this->documentoSalida('Resolución de desistimiento', $html, $expediente, 'resolucion', $usuario);
        $this->libro->asentar('salida', 'Resolución de desistimiento', $expediente, $expediente->titular, null, [$documento]);
        $this->notificador->poner($expediente, $expediente->presentador, 'Resolución de desistimiento', $documento);

        $expediente->requerimientos()->where('estado', 'pendiente')->update(['estado' => 'vencido']);
        $expediente->liquidaciones()->where('estado', 'pendiente')->update(['estado' => 'anulada']);
        $expediente->update(['resultado' => 'desistida', 'resuelto_at' => now(), 'desistimiento_propuesto_at' => null]);
        $this->cambiarEstado($expediente, EstadoExpediente::Desistida, 'Desistimiento confirmado por el personal.', 'usuario', $usuario->id);
    }

    /** Desistimiento voluntario de la persona interesada antes de la resolución. */
    public function retirar(Expediente $expediente, Persona $persona, ?string $motivo = null): Registro
    {
        if ($expediente->estado->esFinal() || $expediente->estado === EstadoExpediente::Borrador) {
            throw new DomainException('Esta solicitud ya no se puede retirar.');
        }
        $registro = $this->libro->asentar('entrada', 'Desistimiento de la solicitud', $expediente, $persona, $motivo);
        $expediente->requerimientos()->where('estado', 'pendiente')->update(['estado' => 'anulado']);
        $expediente->liquidaciones()->where('estado', 'pendiente')->update(['estado' => 'anulada']);
        $expediente->update(['resultado' => 'retirada', 'resuelto_at' => now()]);
        $this->cambiarEstado($expediente, EstadoExpediente::Cerrada, 'Solicitud retirada por la persona interesada. Registro '.$registro->codigo().'.', 'persona', $persona->id);

        return $registro;
    }

    /** Resolución con plantilla y sello electrónico, registro de salida, notificación y cierre. */
    public function resolver(Expediente $expediente, User $usuario, Plantilla $plantilla, string $resultado, ?string $motivo = null): Documento
    {
        if (! array_key_exists($resultado, ['aprobada' => 1, 'denegada' => 1, 'anulada' => 1])) {
            throw new DomainException('Resultado no válido.');
        }
        if ($resultado === 'denegada' && trim((string) $motivo) === '') {
            throw new DomainException('La denegación tiene que ser motivada.');
        }

        $html = $plantilla->rellenar($this->valoresPlantilla($expediente, $motivo));
        $documento = $this->documentoSalida($plantilla->asunto ?: $plantilla->nombre, $html, $expediente, 'resolucion', $usuario);
        $this->libro->asentar('salida', $plantilla->asunto ?: $plantilla->nombre, $expediente, $expediente->titular, null, [$documento]);
        $this->notificador->poner($expediente, $expediente->presentador, $plantilla->asunto ?: $plantilla->nombre, $documento);

        $expediente->update(['resultado' => $resultado, 'resuelto_at' => now()]);
        $this->cambiarEstado($expediente, EstadoExpediente::Cerrada, 'Resolución: '.(Expediente::RESULTADOS[$resultado] ?? $resultado).'.', 'usuario', $usuario->id);

        return $documento;
    }

    /** @return array<string, string> */
    public function valoresPlantilla(Expediente $expediente, ?string $motivo = null): array
    {
        $titular = $expediente->titular;

        return [
            'expediente.numero' => e((string) $expediente->numero),
            'expediente.procedimiento' => e($expediente->procedimiento->nombre),
            'expediente.fecha_presentacion' => $expediente->presentado_at?->format('d/m/Y') ?? '',
            'titular.nombre' => e($titular->nombreCompleto()),
            'titular.nif' => e($titular->nif),
            'organismo.nombre' => e(Organismo::nombre()),
            'fecha' => now()->format('d/m/Y'),
            'motivo' => nl2br(e((string) $motivo)),
        ];
    }

    private function documentoSalida(string $titulo, string $html, Expediente $expediente, string $categoria, User $usuario): Documento
    {
        $csv = $this->almacen->nuevoCsv();
        $contenido = $this->pdf->generar($titulo, $html, $csv);

        return $this->almacen->guardarContenido($contenido, 'pdf', $expediente, $categoria, [
            'nombre' => $titulo.'.pdf',
            'csv' => $csv,
            'origen' => 'organismo',
            'user_id' => $usuario->id,
            'firmado' => $this->pdf->estaSellando(),
        ]);
    }
}
