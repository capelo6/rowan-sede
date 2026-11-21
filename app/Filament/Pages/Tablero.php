<?php

namespace App\Filament\Pages;

use App\Enums\EstadoExpediente;
use App\Filament\Resources\ExpedienteResource;
use App\Filament\Support\Permiso;
use App\Models\Expediente;
use App\Models\Procedimiento;
use App\Sede\Plazos\Calendario;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Bandeja en vista de tablero (kanban): una columna por estado abierto. */
class Tablero extends Page
{
    protected string $view = 'filament.pages.tablero';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Tramitación';

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $title = 'Tablero de expedientes';

    protected static ?int $navigationSort = 2;

    public ?string $procedimiento = null;

    public bool $mios = false;

    public static function canAccess(): bool
    {
        return Permiso::tiene('expedientes.ver');
    }

    /** @return array<string, array{etiqueta: string, color: string, tarjetas: list<array<string, mixed>>}> */
    public function getColumnas(): array
    {
        $cal = app(Calendario::class);
        $estados = [EstadoExpediente::Nueva, EstadoExpediente::EnRevision, EstadoExpediente::EsperandoDocumentacion,
            EstadoExpediente::PendientePago, EstadoExpediente::Pagado, EstadoExpediente::EnCurso];
        $q = Expediente::query()->whereIn('estado', array_map(fn ($e) => $e->value, $estados))->with(['procedimiento', 'titular', 'asignado']);
        if ($this->procedimiento) {
            $q->where('procedimiento_id', $this->procedimiento);
        }
        if ($this->mios) {
            $q->where('asignado_a', auth()->id());
        }
        $columnas = [];
        foreach ($estados as $e) {
            $columnas[$e->value] = ['etiqueta' => $e->etiqueta(), 'color' => $e->color(), 'tarjetas' => []];
        }
        foreach ($q->orderBy('presentado_at')->limit(500)->get() as $exp) {
            $vence = ExpedienteResource::vencimiento($exp);
            $columnas[$exp->estado->value]['tarjetas'][] = [
                'numero' => $exp->numero,
                'tramite' => $exp->procedimiento->nombre,
                'titular' => $exp->titular->nombreCompleto(),
                'asignado' => $exp->asignado?->name,
                'vence' => $vence?->format('d/m/Y'),
                'semaforo' => $cal->semaforo($vence),
                'desistimiento' => $exp->desistimiento_propuesto_at !== null,
                'url' => ExpedienteResource::getUrl('view', ['record' => $exp]),
            ];
        }

        return $columnas;
    }

    /** @return array<int, string> */
    public function getProcedimientos(): array
    {
        return Procedimiento::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }
}
