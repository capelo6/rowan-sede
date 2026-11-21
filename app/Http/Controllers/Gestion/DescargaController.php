<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Expediente;
use App\Sede\Expedientes\ExportadorExpediente;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descargas del panel. Cada descarga queda en la auditoría. */
class DescargaController extends Controller
{
    public function documento(Documento $documento): StreamedResponse
    {
        abort_unless(auth('web')->user()?->can('expedientes.ver') || auth('web')->user()?->can('representaciones.validar'), 403);
        activity('documentos')->causedBy(auth('web')->user())->performedOn($documento->expediente ?? $documento)
            ->event('descarga')->withProperties(['documento_id' => $documento->id, 'nombre' => $documento->nombre])->log('Descarga de documento: '.$documento->nombre);

        return Storage::disk($documento->disco)->download($documento->ruta, $documento->nombre, ['Content-Type' => $documento->mime ?: 'application/octet-stream']);
    }

    public function zip(Expediente $expediente, ExportadorExpediente $exportador): BinaryFileResponse
    {
        abort_unless(auth('web')->user()?->can('expedientes.ver'), 403);
        activity('expedientes')->causedBy(auth('web')->user())->performedOn($expediente)->event('descarga')->log('Descarga del expediente completo (ZIP)');

        return response()->download($exportador->zip($expediente), 'Expediente '.Str::slug(str_replace('/', '-', (string) $expediente->numero)).'.zip')->deleteFileAfterSend();
    }
}
