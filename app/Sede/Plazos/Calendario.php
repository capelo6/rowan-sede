<?php

namespace App\Sede\Plazos;

use App\Models\Festivo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cómputo de plazos según el art. 30 de la Ley 39/2015:
 * - En días hábiles se excluyen sábados, domingos y festivos.
 * - El plazo empieza el día siguiente al de la notificación o publicación.
 * - Si el último día es inhábil, el plazo termina el primer día hábil siguiente.
 */
class Calendario
{
    /** @var array<string, true>|null */
    private ?array $festivos = null;

    public function esHabil(CarbonInterface $dia): bool
    {
        if ($dia->isWeekend()) {
            return false;
        }

        return ! isset($this->festivos()[$dia->toDateString()]);
    }

    /** Fecha de vencimiento de un plazo de $dias hábiles que empieza a contar el día siguiente a $desde. */
    public function venceHabiles(CarbonInterface $desde, int $dias): CarbonImmutable
    {
        $dia = CarbonImmutable::parse($desde)->startOfDay();
        $contados = 0;
        while ($contados < $dias) {
            $dia = $dia->addDay();
            if ($this->esHabil($dia)) {
                $contados++;
            }
        }

        return $dia;
    }

    /** Fecha de vencimiento de un plazo en días naturales; si cae en inhábil, pasa al siguiente hábil. */
    public function venceNaturales(CarbonInterface $desde, int $dias, bool $trasladarInhabil = true): CarbonImmutable
    {
        $dia = CarbonImmutable::parse($desde)->startOfDay()->addDays($dias);
        while ($trasladarInhabil && ! $this->esHabil($dia)) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /** Días hábiles que quedan desde hoy hasta $vence, incluido el último. Negativo si ya venció. */
    public function habilesRestantes(CarbonInterface $vence, ?CarbonInterface $hoy = null): int
    {
        $hoy = CarbonImmutable::parse($hoy ?? now())->startOfDay();
        $vence = CarbonImmutable::parse($vence)->startOfDay();
        if ($vence->lt($hoy)) {
            return -1 * $hoy->diffInDays($vence, true);
        }
        $n = 0;
        for ($d = $hoy; $d->lte($vence); $d = $d->addDay()) {
            if ($this->esHabil($d)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Nivel visual del aviso: «urgente» (plazo próximo a vencer o vencido),
     * «pendiente» (acción requerida con margen) o «en_orden» (sin acciones).
     */
    public function semaforo(?CarbonInterface $vence, ?CarbonInterface $hoy = null): string
    {
        if ($vence === null) {
            return 'en_orden';
        }

        return $this->habilesRestantes($vence, $hoy) <= (int) config('sede.plazos.urgente_dias', 3) ? 'urgente' : 'pendiente';
    }

    /** Fecha a mitad de un plazo (para los recordatorios). */
    public function mitad(CarbonInterface $inicio, CarbonInterface $vence): CarbonImmutable
    {
        $inicio = CarbonImmutable::parse($inicio)->startOfDay();
        $vence = CarbonImmutable::parse($vence)->startOfDay();

        return $inicio->addDays(intdiv((int) $inicio->diffInDays($vence, true), 2));
    }

    public function olvidar(): void
    {
        $this->festivos = null;
        Cache::forget('sede.festivos');
    }

    /** @return array<string, true> */
    private function festivos(): array
    {
        return $this->festivos ??= Cache::remember('sede.festivos', 3600, function () {
            $mapa = [];
            foreach (Festivo::query()->pluck('fecha') as $fecha) {
                $mapa[CarbonImmutable::parse($fecha)->toDateString()] = true;
            }

            return $mapa;
        });
    }
}
