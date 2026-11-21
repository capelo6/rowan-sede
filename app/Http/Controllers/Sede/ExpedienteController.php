<?php

namespace App\Http\Controllers\Sede;

use App\Enums\EstadoExpediente;
use App\Models\Expediente;
use App\Sede\Documentos\Almacen;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Plazos\Calendario;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpedienteController extends Controlador
{
    public function __construct(
        private readonly Tramitador $tramitador,
        private readonly Almacen $almacen,
        private readonly Calendario $calendario,
    ) {}

    public function ver(Expediente $expediente): View|RedirectResponse
    {
        $this->comprobarExpediente($expediente);
        if ($expediente->estado === EstadoExpediente::Borrador) {
            return redirect()->route('sede.solicitudes.editar', $expediente);
        }
        $bloqueados = $this->persona()->notificaciones()->where('estado', 'pendiente')->pluck('documento_id')->filter()->all();

        return view('sede.expedientes.ver', [
            'expediente' => $expediente->load(['procedimiento', 'titular', 'presentador', 'historial']),
            'documentos' => $expediente->documentos()->whereNotIn('id', $bloqueados)->where('categoria', '!=', 'firma')->latest()->get(),
            'requerimiento' => $expediente->requerimientoPendiente(),
            'liquidacion' => $expediente->liquidacionPendiente()?->load('lineas'),
            'notificaciones' => $expediente->notificaciones()->where('persona_id', $this->persona()->id)->latest()->get(),
            'calendario' => $this->calendario,
            'bizum' => (bool) config('sede.pagos.redsys.bizum'),
        ]);
    }

    /** Subsanación: la persona ve únicamente lo que se le ha requerido. */
    public function subsanar(Expediente $expediente): View|RedirectResponse
    {
        $this->comprobarExpediente($expediente);
        $requerimiento = $expediente->requerimientoPendiente();
        if (! $requerimiento) {
            return redirect()->route('sede.expedientes.ver', $expediente);
        }

        return view('sede.expedientes.subsanar', compact('expediente', 'requerimiento') + ['calendario' => $this->calendario]);
    }

    public function enviarSubsanacion(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->comprobarExpediente($expediente);
        $requerimiento = $expediente->requerimientoPendiente();
        abort_if($requerimiento === null, 404);

        $reglas = [];
        foreach ($requerimiento->itemsDocumento() as $item) {
            $reglas['ficheros.'.$item['clave']] = ['required', 'array', 'min:1'];
            $reglas['ficheros.'.$item['clave'].'.*'] = ['file', 'mimes:'.implode(',', config('sede.documentos.formatos')), 'max:'.(int) config('sede.documentos.max_mb', 10) * 1024];
        }
        foreach ($requerimiento->itemsDato() as $item) {
            $reglas['datos.'.$item['clave']] = ['required', 'string', 'max:2000'];
        }
        $request->validate($reglas, ['ficheros.*.required' => 'Aporte este documento.', 'datos.*.required' => 'Indique este dato.']);

        $documentos = [];
        foreach ($requerimiento->itemsDocumento() as $item) {
            foreach ((array) $request->file('ficheros.'.$item['clave'], []) as $fichero) {
                $documentos[] = $this->almacen->guardarSubida($fichero, $expediente, 'aportado', [
                    'clave_requisito' => $item['clave'],
                    'nombre' => $item['descripcion'].' - '.$fichero->getClientOriginalName(),
                    'persona_id' => $this->persona()->id,
                    'metadatos' => ['requerimiento_id' => $requerimiento->id],
                ]);
            }
        }
        $datos = [];
        foreach ($requerimiento->itemsDato() as $item) {
            $datos[$item['clave']] = (string) $request->input('datos.'.$item['clave']);
        }

        try {
            $registro = $this->tramitador->atenderRequerimiento($requerimiento, $this->persona(), $documentos, $datos);
        } catch (DomainException $e) {
            return back()->withErrors(['subsanacion' => $e->getMessage()]);
        }

        return redirect()->route('sede.expedientes.ver', $expediente)->with('aviso', 'Documentación enviada. Registro de entrada '.$registro->codigo().'.');
    }

    public function confirmarRetirada(Expediente $expediente): View
    {
        $this->comprobarExpediente($expediente);
        abort_if($expediente->estado->esFinal(), 404);

        return view('sede.expedientes.retirar', compact('expediente'));
    }

    public function retirar(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->comprobarExpediente($expediente);
        $request->validate(['motivo' => ['nullable', 'string', 'max:1000'], 'confirmo' => ['accepted']],
            ['confirmo.accepted' => 'Marque la casilla para confirmar que quiere retirar la solicitud.']);
        try {
            $registro = $this->tramitador->retirar($expediente, $this->persona(), $request->input('motivo'));
        } catch (DomainException $e) {
            return back()->withErrors(['retirada' => $e->getMessage()]);
        }

        return redirect()->route('sede.expedientes.ver', $expediente)->with('aviso', 'Ha retirado su solicitud. Registro de entrada '.$registro->codigo().'.');
    }
}
