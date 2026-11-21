<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoExpediente;
use App\Filament\Resources\ExpedienteResource;
use App\Models\Expediente;
use App\Models\ExpedienteEstado;
use App\Models\Liquidacion;
use App\Models\Pago;
use App\Models\Requerimiento;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Pantalla de inicio: solicitudes nuevas, en revisión, documentación recibida, pagos y plazos. */
class ResumenTramitacion extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $url = ExpedienteResource::getUrl('index');
        $limite = now()->addDays(5)->toDateString();
        $proximos = Requerimiento::query()->where('estado', 'pendiente')->whereDate('vence_at', '<=', $limite)->count()
            + Liquidacion::query()->where('estado', 'pendiente')->whereDate('vence_at', '<=', $limite)->count();
        $recibida = ExpedienteEstado::query()->where('estado_anterior', EstadoExpediente::EsperandoDocumentacion->value)
            ->where('estado_nuevo', EstadoExpediente::EnRevision->value)->where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Solicitudes nuevas', (string) Expediente::query()->where('estado', EstadoExpediente::Nueva->value)->count())
                ->description('Sin revisar')->color('info')->url($url),
            Stat::make('En revisión', (string) Expediente::query()->where('estado', EstadoExpediente::EnRevision->value)->count())
                ->description(Expediente::query()->where('estado', EstadoExpediente::EnRevision->value)->where('asignado_a', auth()->id())->count().' asignadas a mí')->url($url),
            Stat::make('Documentación recibida', (string) $recibida)->description('Subsanaciones de los últimos 7 días')->color('success')->url($url),
            Stat::make('Pagos pendientes', (string) Liquidacion::query()->where('estado', 'pendiente')->count())
                ->description(Pago::query()->where('estado', 'autorizado')->whereDate('pagado_at', today())->count().' cobrados hoy')->color('warning'),
            Stat::make('Plazos próximos', (string) $proximos)->description('Vencen en 5 días o menos')->color($proximos ? 'danger' : 'gray'),
            Stat::make('Desistimientos propuestos', (string) Expediente::query()->whereNotNull('desistimiento_propuesto_at')
                ->whereNotIn('estado', [EstadoExpediente::Cerrada->value, EstadoExpediente::Desistida->value])->count())
                ->description('Pendientes de confirmar')->color('danger'),
        ];
    }
}
