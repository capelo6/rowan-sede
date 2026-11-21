<?php

namespace App\Sede\Registro;

use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Persona;
use App\Models\Registro;
use App\Sede\Documentos\Almacen;
use App\Sede\Documentos\GeneradorPdf;
use Illuminate\Support\Facades\DB;

/**
 * Registro electrónico de entrada y salida: numeración correlativa por tipo y ejercicio,
 * fecha y hora exactas y justificante con CSV.
 */
class LibroRegistro
{
    public function __construct(
        private readonly Almacen $almacen,
        private readonly GeneradorPdf $pdf,
    ) {}

    /**
     * @param  list<Documento>  $documentos  Documentos que acompañan al asiento (se listan con su huella).
     */
    public function asentar(string $tipo, string $asunto, ?Expediente $expediente, ?Persona $persona, ?string $resumen = null, array $documentos = []): Registro
    {
        $registro = DB::transaction(function () use ($tipo, $asunto, $expediente, $persona, $resumen) {
            $ahora = now();
            $ejercicio = (int) $ahora->format('Y');
            $ultimo = (int) Registro::query()->where('tipo', $tipo)->where('ejercicio', $ejercicio)->lockForUpdate()->max('numero');

            return Registro::query()->create([
                'tipo' => $tipo,
                'ejercicio' => $ejercicio,
                'numero' => $ultimo + 1,
                'registrado_at' => $ahora,
                'expediente_id' => $expediente?->id,
                'persona_id' => $persona?->id,
                'asunto' => $asunto,
                'resumen' => $resumen,
            ]);
        });

        $justificante = $this->generarJustificante($registro, $documentos);
        $registro->update(['justificante_id' => $justificante->id]);

        return $registro->refresh();
    }

    /** @param list<Documento> $documentos */
    private function generarJustificante(Registro $registro, array $documentos): Documento
    {
        $csv = $this->almacen->nuevoCsv();
        $persona = $registro->persona;
        $filas = '';
        foreach ($documentos as $doc) {
            $filas .= '<tr><td>'.e($doc->nombre).'</td><td style="font-size:7pt;">'.e($doc->sha256).'</td></tr>';
        }
        $titulo = $registro->tipo === 'entrada' ? 'Justificante de registro de entrada' : 'Justificante de registro de salida';
        $html = '<table cellpadding="3" border="1" style="border-color:#999999;">'
            .'<tr><td style="width:35%;"><strong>Número de registro</strong></td><td style="width:65%;">'.e($registro->codigo()).'</td></tr>'
            .'<tr><td><strong>Fecha y hora</strong></td><td>'.$registro->registrado_at->format('d/m/Y H:i:s').'</td></tr>'
            .($registro->expediente ? '<tr><td><strong>Expediente</strong></td><td>'.e($registro->expediente->referencia()).'</td></tr>' : '')
            .'<tr><td><strong>Asunto</strong></td><td>'.e($registro->asunto).'</td></tr>'
            .($persona ? '<tr><td><strong>Interesado</strong></td><td>'.e($persona->nombreCompleto()).' · '.e($persona->nif).'</td></tr>' : '')
            .($registro->resumen ? '<tr><td><strong>Extracto</strong></td><td>'.e($registro->resumen).'</td></tr>' : '')
            .'</table>'
            .($filas !== '' ? '<h3 style="font-size:10pt;">Documentos registrados</h3><table cellpadding="3" border="1" style="border-color:#999999;"><tr><th style="width:45%;">Documento</th><th style="width:55%;">Huella SHA-256</th></tr>'.$filas.'</table>' : '');

        $contenido = $this->pdf->generar($titulo, $html, $csv);

        return $this->almacen->guardarContenido($contenido, 'pdf', $registro->expediente, 'justificante_registro', [
            'nombre' => $titulo.' '.$registro->codigo().'.pdf',
            'csv' => $csv,
            'origen' => 'organismo',
            'persona_id' => $registro->persona_id,
            'firmado' => $this->pdf->estaSellando(),
            'metadatos' => ['registro_id' => $registro->id],
        ]);
    }
}
