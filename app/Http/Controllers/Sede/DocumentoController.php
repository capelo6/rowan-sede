<?php

namespace App\Http\Controllers\Sede;

use App\Models\Documento;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controlador
{
    public function descargar(Documento $documento): StreamedResponse
    {
        $persona = $this->persona();
        $accesible = $documento->expediente
            ? $documento->expediente->esAccesiblePara($persona)
            : $documento->persona_id === $persona->id;
        abort_unless($accesible, 404);

        // El contenido de una notificación solo se ve después de comparecer.
        $pendiente = Notificacion::query()->where('documento_id', $documento->id)->where('estado', 'pendiente')->exists();
        abort_if($pendiente, 403, 'Abra primero la notificación desde su buzón.');

        activity('documentos')->causedBy($persona)->performedOn($documento)->event('descarga')->log('Descarga de documento');

        return Storage::disk($documento->disco)->download($documento->ruta, $documento->nombre, [
            'Content-Type' => $documento->mime ?: 'application/octet-stream',
        ]);
    }
}
