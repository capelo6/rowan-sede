<?php

namespace App\Http\Controllers\Sede;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\Persona;

abstract class Controlador extends Controller
{
    protected function persona(): Persona
    {
        /** @var Persona $persona */
        $persona = auth('sede')->user();

        return $persona;
    }

    protected function comprobarExpediente(Expediente $expediente): void
    {
        abort_unless($expediente->esAccesiblePara($this->persona()), 404);
    }
}
