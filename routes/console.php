<?php

use Illuminate\Support\Facades\Schedule;

// Notificaciones rechazadas por plazo, propuestas de desistimiento y recordatorios por correo.
Schedule::command('sede:tareas')->hourly()->withoutOverlapping();
