<?php

namespace App\Http\Controllers\Sede;

use App\Enums\EstadoExpediente;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Procedimiento;
use App\Models\Representacion;
use App\Sede\Documentos\Almacen;
use App\Sede\Documentos\GeneradorPdf;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Identidad\AccesoDenegado;
use App\Sede\Identidad\VerificadorFirma;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Presentación de solicitudes: borrador con guardado automático, documentos, resumen y firma con AutoFirma. */
class SolicitudController extends Controlador
{
    public function __construct(
        private readonly Tramitador $tramitador,
        private readonly Almacen $almacen,
        private readonly GeneradorPdf $pdf,
        private readonly VerificadorFirma $verificador,
    ) {}

    public function crear(Request $request, Procedimiento $procedimiento): RedirectResponse
    {
        abort_unless($procedimiento->activo, 404);
        $representacion = null;
        if ($request->filled('representacion_id')) {
            $representacion = Representacion::query()->where('representante_id', $this->persona()->id)->findOrFail($request->integer('representacion_id'));
            abort_unless($representacion->vigente(), 403, 'La representación no está validada o ha caducado.');
        }
        try {
            $expediente = $this->tramitador->crearBorrador($procedimiento, $this->persona(), $representacion);
        } catch (DomainException $e) {
            return back()->withErrors(['solicitud' => $e->getMessage()]);
        }

        return redirect()->route('sede.solicitudes.editar', $expediente);
    }

    public function editar(Expediente $expediente): View
    {
        $this->comprobarBorrador($expediente);

        return view('sede.solicitudes.editar', [
            'expediente' => $expediente->load(['procedimiento', 'titular']),
            'documentos' => $expediente->documentos()->where('categoria', 'aportado')->get()->groupBy('clave_requisito'),
        ]);
    }

    /** Guardado del borrador (automático por JavaScript o con el botón). */
    public function guardar(Request $request, Expediente $expediente): JsonResponse|RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        $claves = collect($expediente->procedimiento->campos())->pluck('clave')->all();
        $entrada = (array) $request->input('datos', []);
        $datos = [];
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $entrada)) {
                $datos[$clave] = is_scalar($entrada[$clave]) ? mb_substr(trim((string) $entrada[$clave]), 0, 2000) : null;
            }
        }
        $expediente->update(['datos' => array_merge($expediente->datos ?? [], $datos)]);

        if ($request->expectsJson()) {
            return response()->json(['guardado' => now()->format('H:i')]);
        }
        if ($request->input('siguiente') === 'resumen') {
            return redirect()->route('sede.solicitudes.resumen', $expediente);
        }

        return back()->with('aviso', 'Borrador guardado a las '.now()->format('H:i').'.');
    }

    public function subirDocumento(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        $requisito = $expediente->procedimiento->documentoRequerido((string) $request->input('clave'));
        abort_if($requisito === null, 422);
        $request->validate(['fichero' => $this->reglasFichero($requisito)], [], ['fichero' => $requisito['nombre']]);

        $this->almacen->guardarSubida($request->file('fichero'), $expediente, 'aportado', [
            'clave_requisito' => $requisito['clave'],
            'nombre' => $requisito['nombre'].' - '.$request->file('fichero')->getClientOriginalName(),
            'persona_id' => $this->persona()->id,
            'origen' => 'interesado',
        ]);

        return redirect()->to(route('sede.solicitudes.editar', $expediente).'#doc-'.$requisito['clave'])->with('aviso', 'Documento añadido.');
    }

    public function quitarDocumento(Expediente $expediente, Documento $documento): RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        abort_unless($documento->expediente_id === $expediente->id && $documento->categoria === 'aportado', 404);
        Storage::disk($documento->disco)->delete($documento->ruta);
        $documento->delete();

        return back()->with('aviso', 'Documento quitado.');
    }

    public function resumen(Expediente $expediente): View|RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        $errores = $this->comprobarCompleta($expediente);
        if ($errores !== []) {
            return redirect()->route('sede.solicitudes.editar', $expediente)->withErrors($errores);
        }

        return view('sede.solicitudes.resumen', [
            'expediente' => $expediente->load(['procedimiento', 'titular', 'presentador']),
            'documentos' => $expediente->documentos()->where('categoria', 'aportado')->get(),
            'simulado' => AccesoController::simuladoActivo(),
        ]);
    }

    /** Genera el PDF de la solicitud que la persona firmará con AutoFirma. */
    public function prepararFirma(Expediente $expediente): JsonResponse
    {
        $this->comprobarBorrador($expediente);
        if ($this->comprobarCompleta($expediente) !== []) {
            return response()->json(['error' => 'La solicitud no está completa.'], 422);
        }
        $expediente->documentos()->where('categoria', 'solicitud')->where('firmado', false)->get()
            ->each(function (Documento $d) {
                Storage::disk($d->disco)->delete($d->ruta);
                $d->delete();
            });
        $contenido = $this->pdf->generar('Solicitud: '.$expediente->procedimiento->nombre, $this->htmlSolicitud($expediente), null, false);
        $documento = $this->almacen->guardarContenido($contenido, 'pdf', $expediente, 'solicitud', [
            'nombre' => 'Solicitud - '.$expediente->procedimiento->nombre.'.pdf',
            'persona_id' => $this->persona()->id,
            'origen' => 'interesado',
        ]);

        return response()->json(['documento' => $documento->id, 'datos' => base64_encode($contenido)]);
    }

    public function presentar(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        $request->validate(['documento' => ['required', 'integer'], 'firma' => ['nullable', 'string', 'max:2000000']]);
        $solicitud = $expediente->documentos()->where('categoria', 'solicitud')->findOrFail($request->integer('documento'));
        $persona = $this->persona();

        if ($request->boolean('simulada') && AccesoController::simuladoActivo()) {
            $firma = $this->almacen->guardarContenido('FIRMA SIMULADA (modo de demostración)', 'txt', $expediente, 'firma', [
                'nombre' => 'Firma simulada de la solicitud.txt', 'persona_id' => $persona->id, 'metadatos' => ['simulada' => true]]);
        } else {
            try {
                $certificado = $this->verificador->verificar((string) $request->input('firma'), $solicitud->contenido());
            } catch (AccesoDenegado $e) {
                return back()->withErrors(['firma' => $e->getMessage()]);
            }
            if ($certificado->nif !== $persona->nif) {
                return back()->withErrors(['firma' => 'La solicitud se tiene que firmar con el mismo certificado con el que ha accedido.']);
            }
            $firma = $this->almacen->guardarContenido((string) base64_decode((string) $request->input('firma')), 'csig', $expediente, 'firma', [
                'nombre' => 'Firma CAdES de la solicitud.csig',
                'persona_id' => $persona->id,
                'firmado' => true,
                'metadatos' => ['documento_firmado' => $solicitud->id, 'certificado' => $certificado->resumen(), 'nif' => $certificado->nif],
            ]);
        }
        $solicitud->update(['firmado' => true, 'metadatos' => ['firma_id' => $firma->id]]);

        $aportados = $expediente->documentos()->where('categoria', 'aportado')->get()->all();
        $registro = $this->tramitador->presentar($expediente, array_merge([$solicitud, $firma], $aportados));

        return redirect()->route('sede.expedientes.ver', $expediente)
            ->with('aviso', 'Solicitud presentada. Número de expediente '.$expediente->numero.', registro '.$registro->codigo().'.');
    }

    public function descartar(Expediente $expediente): RedirectResponse
    {
        $this->comprobarBorrador($expediente);
        foreach ($expediente->documentos as $d) {
            Storage::disk($d->disco)->delete($d->ruta);
            $d->delete();
        }
        $expediente->delete();

        return redirect()->route('sede.carpeta')->with('aviso', 'Se ha descartado la solicitud.');
    }

    private function comprobarBorrador(Expediente $expediente): void
    {
        abort_unless($expediente->presentador_id === $this->persona()->id, 404);
        abort_unless($expediente->estado === EstadoExpediente::Borrador, 404);
    }

    /** @return array<string, string> */
    private function comprobarCompleta(Expediente $expediente): array
    {
        $reglas = [];
        $nombres = [];
        foreach ($expediente->procedimiento->campos() as $campo) {
            $r = [($campo['obligatorio'] ?? false) ? 'required' : 'nullable'];
            match ($campo['tipo'] ?? 'texto') {
                'email' => $r[] = 'email',
                'fecha' => $r[] = 'date',
                'numero' => $r[] = 'numeric',
                'nif' => $r[] = function ($a, $v, $fail) {
                    if ($v && ! \App\Sede\Identidad\CertificadoEspanol::nifValido(strtoupper((string) $v))) {
                        $fail('El NIF no es válido.');
                    }
                },
                'opcion' => $r[] = 'in:'.implode(',', array_map(fn ($o) => is_array($o) ? $o['valor'] : $o, $campo['opciones'] ?? [])),
                default => null,
            };
            $reglas['datos.'.$campo['clave']] = $r;
            $nombres['datos.'.$campo['clave']] = $campo['etiqueta'];
        }
        $errores = Validator::make(['datos' => $expediente->datos ?? []], $reglas, [], $nombres)->errors()->toArray();
        $errores = array_map(fn ($m) => $m[0], $errores);

        $aportados = $expediente->documentos()->where('categoria', 'aportado')->pluck('clave_requisito')->all();
        foreach ($expediente->procedimiento->documentosRequeridos() as $doc) {
            if (($doc['obligatorio'] ?? false) && ! in_array($doc['clave'], $aportados, true)) {
                $errores['doc-'.$doc['clave']] = 'Falta el documento «'.$doc['nombre'].'».';
            }
        }

        return $errores;
    }

    /** @return list<mixed> */
    private function reglasFichero(array $requisito): array
    {
        $formatos = $requisito['formatos'] ?? config('sede.documentos.formatos');
        $maxKb = (int) (($requisito['max_mb'] ?? config('sede.documentos.max_mb', 10)) * 1024);

        return ['required', 'file', 'mimes:'.implode(',', $formatos), 'max:'.$maxKb];
    }

    private function htmlSolicitud(Expediente $expediente): string
    {
        $p = $expediente->presentador;
        $t = $expediente->titular;
        $filas = '<tr><td style="width:35%;"><strong>Procedimiento</strong></td><td style="width:65%;">'.e($expediente->procedimiento->nombre).'</td></tr>'
            .'<tr><td><strong>Presenta</strong></td><td>'.e($p->nombreCompleto()).' · '.e($p->nif).'</td></tr>'
            .($t->id !== $p->id ? '<tr><td><strong>En representación de</strong></td><td>'.e($t->nombreCompleto()).' · '.e($t->nif).'</td></tr>' : '')
            .'<tr><td><strong>Correo de aviso</strong></td><td>'.e((string) $p->email).'</td></tr>';
        foreach ($expediente->procedimiento->campos() as $campo) {
            $valor = $expediente->datos[$campo['clave']] ?? '';
            if (($campo['tipo'] ?? '') === 'si_no') {
                $valor = $valor ? 'Sí' : 'No';
            }
            $filas .= '<tr><td><strong>'.e($campo['etiqueta']).'</strong></td><td>'.nl2br(e((string) $valor)).'</td></tr>';
        }
        $docs = '';
        foreach ($expediente->documentos()->where('categoria', 'aportado')->get() as $d) {
            $docs .= '<tr><td>'.e($d->nombre).'</td><td style="font-size:7pt;">'.e($d->sha256).'</td></tr>';
        }

        return '<table cellpadding="3" border="1" style="border-color:#999999;">'.$filas.'</table>'
            .($docs !== '' ? '<h3 style="font-size:10pt;">Documentos que se aportan</h3><table cellpadding="3" border="1" style="border-color:#999999;"><tr><th style="width:45%;">Documento</th><th style="width:55%;">Huella SHA-256</th></tr>'.$docs.'</table>' : '')
            .'<p>Declaro que los datos y documentos aportados son ciertos. Firmo esta solicitud con mi certificado electrónico.</p>';
    }
}
