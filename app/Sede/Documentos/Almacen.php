<?php

namespace App\Sede\Documentos;

use App\Models\Documento;
use App\Models\Expediente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda los documentos del expediente en el disco privado, con su huella SHA-256.
 * Los documentos que genera el organismo llevan además un código seguro de verificación (CSV).
 */
class Almacen
{
    public function disco(): string
    {
        return (string) config('sede.documentos.disco', 'local');
    }

    /** @param array<string, mixed> $atributos */
    public function guardarSubida(UploadedFile $fichero, ?Expediente $expediente, string $categoria, array $atributos = []): Documento
    {
        $contenido = (string) file_get_contents($fichero->getRealPath());
        $extension = strtolower($fichero->getClientOriginalExtension() ?: $fichero->extension() ?: 'bin');

        return $this->guardarContenido($contenido, $extension, $expediente, $categoria, array_merge([
            'nombre' => $atributos['nombre'] ?? $fichero->getClientOriginalName(),
            'nombre_original' => $fichero->getClientOriginalName(),
            'mime' => $fichero->getMimeType(),
        ], $atributos));
    }

    /** @param array<string, mixed> $atributos */
    public function guardarContenido(string $contenido, string $extension, ?Expediente $expediente, string $categoria, array $atributos = []): Documento
    {
        $carpeta = $expediente ? 'expedientes/'.$expediente->id : 'sueltos/'.now()->format('Y/m');
        $ruta = $carpeta.'/'.Str::uuid()->toString().'.'.ltrim($extension, '.');
        Storage::disk($this->disco())->put($ruta, $contenido);

        return Documento::query()->create(array_merge([
            'expediente_id' => $expediente?->id,
            'categoria' => $categoria,
            'nombre' => $atributos['nombre'] ?? basename($ruta),
            'disco' => $this->disco(),
            'ruta' => $ruta,
            'mime' => $atributos['mime'] ?? $this->mimePorExtension($extension),
            'tamano' => strlen($contenido),
            'sha256' => hash('sha256', $contenido),
            'origen' => $atributos['origen'] ?? 'interesado',
        ], $atributos, ['ruta' => $ruta, 'tamano' => strlen($contenido), 'sha256' => hash('sha256', $contenido)]));
    }

    /** Código seguro de verificación único: PREFIJO-XXXX-XXXX-XXXX-XXXX. */
    public function nuevoCsv(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $bloques = [];
            for ($b = 0; $b < 4; $b++) {
                $bloque = '';
                for ($i = 0; $i < 4; $i++) {
                    $bloque .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $bloques[] = $bloque;
            }
            $csv = config('sede.documentos.prefijo_csv', 'SEDE').'-'.implode('-', $bloques);
        } while (Documento::query()->where('csv', $csv)->exists());

        return $csv;
    }

    public function mimePorExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'csig', 'p7s' => 'application/pkcs7-signature',
            'xml' => 'application/xml',
            'json' => 'application/json',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
