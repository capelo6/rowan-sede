<?php

namespace Tests\Unit;

use App\Models\Festivo;
use App\Sede\Plazos\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_diez_dias_habiles_saltan_fines_de_semana_y_festivos(): void
    {
        // Lunes 30/11/2026; martes 08/12 es festivo nacional.
        Festivo::query()->create(['fecha' => '2026-12-08', 'descripcion' => 'Inmaculada Concepción', 'ambito' => 'nacional']);
        $cal = new Calendario;

        $vence = $cal->venceHabiles(CarbonImmutable::parse('2026-11-30'), 10);

        // 1/12 a 4/12 (4), 7/12 (5), 9/12 a 11/12 (8), 14/12 y 15/12 (10)
        $this->assertSame('2026-12-15', $vence->toDateString());
    }

    public function test_plazo_natural_que_cae_en_domingo_pasa_al_lunes(): void
    {
        $cal = new Calendario;

        $this->assertSame('2026-11-30', $cal->venceNaturales(CarbonImmutable::parse('2026-11-19'), 10)->toDateString());
    }

    public function test_semaforo(): void
    {
        $cal = new Calendario;
        $hoy = CarbonImmutable::parse('2026-11-23');

        $this->assertSame('urgente', $cal->semaforo(CarbonImmutable::parse('2026-11-24'), $hoy));
        $this->assertSame('pendiente', $cal->semaforo(CarbonImmutable::parse('2026-12-04'), $hoy));
        $this->assertSame('en_orden', $cal->semaforo(null, $hoy));
    }
}
