<?php

namespace App\Filament\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exportación sencilla a Excel (xlsx) con OpenSpout. */
class ExportarXlsx
{
    /**
     * @param  list<string>  $cabecera
     * @param  iterable<array<int, mixed>>  $filas
     */
    public static function descargar(string $nombre, array $cabecera, iterable $filas): BinaryFileResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues($cabecera));
        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y H:i:s') : $v, $fila)));
        }
        $writer->close();
        activity('exportaciones')->causedBy(auth()->user())->event('exportacion')->log('Exportación a Excel: '.$nombre);

        return response()->download($ruta, $nombre.' '.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }
}
