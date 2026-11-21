<?php

namespace App\Http\Controllers\Sede;

use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Comprobación de documentos por su código seguro de verificación (CSV). */
class VerificacionController extends Controlador
{
    public function formulario(): View
    {
        return view('sede.verificar', ['documento' => null]);
    }

    public function comprobar(Request $request): View
    {
        $datos = $request->validate(['csv' => ['required', 'string', 'max:40']], [], ['csv' => 'código seguro de verificación']);
        $documento = Documento::query()->where('csv', strtoupper(trim($datos['csv'])))->first();

        return view('sede.verificar', ['documento' => $documento, 'buscado' => true]);
    }

    public function descargar(string $csv): StreamedResponse
    {
        $documento = Documento::query()->where('csv', strtoupper($csv))->firstOrFail();

        return Storage::disk($documento->disco)->download($documento->ruta, $documento->nombre, ['Content-Type' => 'application/pdf']);
    }
}
