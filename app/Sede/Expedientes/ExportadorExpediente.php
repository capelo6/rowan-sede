<?php

namespace App\Sede\Expedientes;

use App\Models\Expediente;
use App\Sede\Organismo;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Expediente completo en un ZIP: los documentos en carpetas por tipo, un índice legible
 * (indice.html) y los metadatos en JSON con la huella SHA-256 de cada documento.
 */
class ExportadorExpediente
{
    public function zip(Expediente $expediente): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'exp').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP.');
        }

        $filas = [];
        $meta = [];
        foreach ($expediente->documentos()->orderBy('created_at')->get() as $i => $doc) {
            $ext = pathinfo($doc->ruta, PATHINFO_EXTENSION);
            $nombre = sprintf('%02d - %s', $i + 1, Str::slug(pathinfo($doc->nombre, PATHINFO_FILENAME), ' ')).($ext ? '.'.$ext : '');
            $carpeta = Str::slug($doc->etiquetaCategoria());
            $zip->addFromString($carpeta.'/'.$nombre, $doc->contenido());
            $filas[] = '<tr><td>'.($i + 1).'</td><td><a href="'.e($carpeta.'/'.$nombre).'">'.e($doc->nombre).'</a></td><td>'.e($doc->etiquetaCategoria())
                .'</td><td>'.$doc->created_at->format('d/m/Y H:i:s').'</td><td>'.e($doc->origen).'</td><td>'.e((string) $doc->csv).'</td><td style="font-family:monospace;font-size:11px;">'.e($doc->sha256).'</td></tr>';
            $meta[] = [
                'orden' => $i + 1, 'fichero' => $carpeta.'/'.$nombre, 'nombre' => $doc->nombre, 'tipo' => $doc->categoria,
                'fecha' => $doc->created_at->toIso8601String(), 'origen' => $doc->origen, 'csv' => $doc->csv, 'sha256' => $doc->sha256, 'firmado' => $doc->firmado,
            ];
        }

        $historial = $expediente->historial->map(fn ($h) => [
            'fecha' => $h->created_at->toIso8601String(), 'estado' => $h->estado_nuevo->value, 'motivo' => $h->motivo, 'actor' => $h->actor_tipo,
        ])->all();

        $zip->addFromString('expediente.json', (string) json_encode([
            'organismo' => Organismo::nombre(),
            'expediente' => $expediente->numero,
            'procedimiento' => $expediente->procedimiento->nombre,
            'titular' => ['nombre' => $expediente->titular->nombreCompleto(), 'nif' => $expediente->titular->nif],
            'estado' => $expediente->estado->value,
            'resultado' => $expediente->resultado,
            'presentado' => $expediente->presentado_at?->toIso8601String(),
            'generado' => now()->toIso8601String(),
            'documentos' => $meta,
            'historial' => $historial,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $zip->addFromString('indice.html', '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Índice del expediente '.e((string) $expediente->numero).'</title>'
            .'<style>body{font-family:Arial,sans-serif;margin:2rem;}table{border-collapse:collapse;width:100%;}td,th{border:1px solid #999;padding:4px 6px;text-align:left;font-size:13px;}</style></head><body>'
            .'<h1>Índice del expediente '.e((string) $expediente->numero).'</h1>'
            .'<p>'.e(Organismo::nombre()).' · '.e($expediente->procedimiento->nombre).' · Titular: '.e($expediente->titular->nombreCompleto()).' ('.e($expediente->titular->nif).')</p>'
            .'<p>Generado el '.now()->format('d/m/Y H:i:s').'. La huella SHA-256 permite comprobar que cada documento no se ha alterado.</p>'
            .'<table><thead><tr><th>N.º</th><th>Documento</th><th>Tipo</th><th>Fecha</th><th>Origen</th><th>CSV</th><th>SHA-256</th></tr></thead><tbody>'
            .implode('', $filas).'</tbody></table></body></html>');

        $zip->close();

        return $ruta;
    }
}
