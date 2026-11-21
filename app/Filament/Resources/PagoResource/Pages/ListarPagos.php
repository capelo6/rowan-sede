<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Filament\Support\ExportarXlsx;
use App\Models\Pago;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListarPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')->label('Exportar a Excel')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn () => ExportarXlsx::descargar('Pagos',
                    ['Fecha', 'Expediente', 'Liquidación', 'Medio', 'Importe', 'Estado', 'Pedido', 'Autorización', 'NIF', 'Titular'],
                    $this->getFilteredTableQuery()->with('liquidacion.expediente.titular')->orderBy('pagado_at')->lazy()->map(fn (Pago $p) => [
                        $p->pagado_at, $p->liquidacion->expediente->numero, $p->liquidacion->referencia, Pago::CANALES[$p->canal] ?? $p->canal,
                        (float) $p->importe, $p->estado, $p->pedido, $p->autorizacion, $p->liquidacion->expediente->titular->nif, $p->liquidacion->expediente->titular->nombreCompleto(),
                    ]))),
        ];
    }
}
