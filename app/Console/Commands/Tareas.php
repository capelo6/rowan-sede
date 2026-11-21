<?php

namespace App\Console\Commands;

use App\Sede\Avisos\ServicioAvisos;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Notificaciones\Notificador;
use Illuminate\Console\Command;

/** Tareas periódicas: notificaciones rechazadas por plazo, propuestas de desistimiento y recordatorios. */
class Tareas extends Command
{
    protected $signature = 'sede:tareas';

    protected $description = 'Ejecuta las tareas periódicas de la sede (programar cada hora)';

    public function handle(Notificador $notificador, Tramitador $tramitador, ServicioAvisos $avisos): int
    {
        $rechazadas = $notificador->rechazarVencidas();
        $propuestas = $tramitador->proponerDesistimientos();
        $enviados = $avisos->programados();
        $this->info("Notificaciones rechazadas: $rechazadas · desistimientos propuestos: $propuestas · avisos enviados: $enviados");

        return self::SUCCESS;
    }
}
