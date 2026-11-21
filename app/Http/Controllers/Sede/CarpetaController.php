<?php

namespace App\Http\Controllers\Sede;

use App\Enums\EstadoExpediente;
use App\Models\Documento;
use App\Models\Expediente;
use App\Sede\Plazos\Calendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarpetaController extends Controlador
{
    public function __construct(private readonly Calendario $calendario) {}

    /** Inicio de la carpeta: avisos con los días que quedan y acceso directo a cada acción pendiente. */
    public function inicio(): View
    {
        $persona = $this->persona();
        $expedientes = $this->misExpedientes()->with(['procedimiento', 'requerimientos', 'liquidaciones'])->latest('updated_at')->get();

        $avisos = collect();
        foreach ($persona->notificaciones()->where('estado', 'pendiente')->get() as $n) {
            $vence = $n->venceAt();
            $avisos->push(['nivel' => $this->calendario->semaforo($vence), 'texto' => 'Tiene una notificación sin abrir: «'.$n->asunto.'».',
                'vence' => $vence, 'accion' => 'Abrir la notificación', 'url' => route('sede.notificaciones.ver', $n)]);
        }
        foreach ($expedientes as $e) {
            if ($e->estado === EstadoExpediente::EsperandoDocumentacion && ($r = $e->requerimientos->firstWhere('estado', 'pendiente'))) {
                $avisos->push(['nivel' => $this->calendario->semaforo($r->vence_at), 'texto' => 'Expediente '.$e->numero.': tiene que aportar documentación.',
                    'vence' => $r->vence_at, 'accion' => 'Aportar documentación', 'url' => route('sede.expedientes.subsanar', $e)]);
            }
            if ($e->estado === EstadoExpediente::PendientePago && ($l = $e->liquidaciones->firstWhere('estado', 'pendiente'))) {
                $avisos->push(['nivel' => $this->calendario->semaforo($l->vence_at), 'texto' => 'Expediente '.$e->numero.': tasa pendiente de '.number_format((float) $l->total, 2, ',', '.').' €.',
                    'vence' => $l->vence_at, 'accion' => 'Pagar la tasa', 'url' => route('sede.expedientes.ver', $e).'#pago']);
            }
            if ($e->estado === EstadoExpediente::Borrador) {
                $avisos->push(['nivel' => 'pendiente', 'texto' => 'Tiene una solicitud de «'.$e->procedimiento->nombre.'» sin enviar.',
                    'vence' => null, 'accion' => 'Continuar la solicitud', 'url' => route('sede.solicitudes.editar', $e)]);
            }
        }
        $avisos = $avisos->sortBy(fn ($a) => [$a['nivel'] === 'urgente' ? 0 : 1, $a['vence']?->timestamp ?? PHP_INT_MAX])->values();

        return view('sede.carpeta.inicio', [
            'avisos' => $avisos,
            'expedientes' => $expedientes->take(5),
            'calendario' => $this->calendario,
        ]);
    }

    public function expedientes(): View
    {
        return view('sede.carpeta.expedientes', [
            'expedientes' => $this->misExpedientes()->with('procedimiento')->latest('updated_at')->paginate(20),
        ]);
    }

    public function documentos(): View
    {
        $ids = $this->misExpedientes()->pluck('id');
        $bloqueados = $this->persona()->notificaciones()->where('estado', 'pendiente')->pluck('documento_id')->filter();

        return view('sede.carpeta.documentos', [
            'documentos' => Documento::query()->whereIn('expediente_id', $ids)->whereNotIn('id', $bloqueados)
                ->where('categoria', '!=', 'firma')->with('expediente')->latest()->paginate(30),
        ]);
    }

    public function perfil(): View
    {
        return view('sede.carpeta.perfil', ['persona' => $this->persona(), 'primerAcceso' => ! $this->persona()->perfilCompleto()]);
    }

    public function guardarPerfil(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'privacidad' => ['accepted'],
            'notificaciones_electronicas' => ['nullable', 'boolean'],
        ], [
            'privacidad.accepted' => 'Tiene que aceptar la política de privacidad para usar la sede.',
        ]);
        $persona = $this->persona();
        $persona->fill(collect($datos)->only(['email', 'telefono', 'direccion', 'codigo_postal', 'municipio', 'provincia'])->all());
        $persona->privacidad_aceptada_at ??= now();
        $persona->consentimiento_notificaciones_at = $request->boolean('notificaciones_electronicas') ? ($persona->consentimiento_notificaciones_at ?? now()) : null;
        $persona->save();

        return redirect()->route('sede.carpeta')->with('aviso', 'Sus datos se han guardado.');
    }

    private function misExpedientes(): Builder
    {
        $id = $this->persona()->id;

        return Expediente::query()->where(fn ($q) => $q->where('titular_id', $id)->orWhere('presentador_id', $id));
    }
}
