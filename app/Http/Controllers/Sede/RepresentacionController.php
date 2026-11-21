<?php

namespace App\Http\Controllers\Sede;

use App\Models\Persona;
use App\Models\Representacion;
use App\Sede\Documentos\Almacen;
use App\Sede\Identidad\CertificadoEspanol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Representaciones: la persona actúa en nombre de otra y aporta el documento, que valida el personal. */
class RepresentacionController extends Controlador
{
    public function __construct(private readonly Almacen $almacen) {}

    public function lista(): View
    {
        $persona = $this->persona();

        return view('sede.carpeta.autorizaciones', [
            'comoRepresentante' => $persona->representacionesComoRepresentante()->with('representado')->latest()->get(),
            'comoRepresentado' => $persona->representacionesComoRepresentado()->with('representante')->latest()->get(),
        ]);
    }

    public function solicitar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nif' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:200'],
            'documento' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.(int) config('sede.documentos.max_mb', 10) * 1024],
        ], [], ['nif' => 'NIF de la persona representada', 'nombre' => 'nombre o razón social', 'documento' => 'documento de representación']);

        $nif = CertificadoEspanol::normalizarNif($datos['nif']);
        if ($nif === null) {
            return back()->withErrors(['nif' => 'El NIF no es válido.'])->withInput();
        }
        if ($nif === $this->persona()->nif) {
            return back()->withErrors(['nif' => 'No puede representarse a sí mismo.'])->withInput();
        }
        $juridica = (bool) preg_match('/^[A-W]/', $nif) && ! preg_match('/^[XYZ]/', $nif);
        $representado = Persona::query()->firstOrCreate(['nif' => $nif], $juridica
            ? ['tipo_persona' => 'juridica', 'razon_social' => $datos['nombre']]
            : ['tipo_persona' => 'fisica', 'nombre' => $datos['nombre']]);

        $representacion = Representacion::query()->create([
            'representante_id' => $this->persona()->id,
            'representado_id' => $representado->id,
            'ambito' => 'todos',
            'estado' => 'pendiente',
        ]);
        $this->almacen->guardarSubida($request->file('documento'), null, 'representacion', [
            'nombre' => 'Representación de '.$datos['nombre'].' - '.$request->file('documento')->getClientOriginalName(),
            'persona_id' => $this->persona()->id,
            'metadatos' => ['representacion_id' => $representacion->id],
        ]);

        return redirect()->route('sede.autorizaciones')->with('aviso', 'Hemos recibido el documento de representación. El personal lo revisará y le avisaremos.');
    }
}
