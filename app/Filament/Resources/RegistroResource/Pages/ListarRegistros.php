<?php

namespace App\Filament\Resources\RegistroResource\Pages;

use App\Filament\Resources\RegistroResource;
use App\Filament\Support\ExportarXlsx;
use App\Models\Registro;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListarRegistros extends ListRecords
{
    protected static string $resource = RegistroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')->label('Exportar a Excel')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn () => ExportarXlsx::descargar('Registro de entrada y salida',
                    ['Número', 'Tipo', 'Fecha y hora', 'Asunto', 'Expediente', 'NIF', 'Interesado'],
                    $this->getFilteredTableQuery()->with(['expediente', 'persona'])->orderBy('registrado_at')->lazy()->map(fn (Registro $r) => [
                        $r->codigo(), $r->tipo, $r->registrado_at, $r->asunto, $r->expediente?->numero, $r->persona?->nif, $r->persona?->nombreCompleto(),
                    ]))),
        ];
    }
}
