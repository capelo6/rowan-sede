<?php

namespace App\Http\Controllers\Sede;

use App\Models\Procedimiento;
use Illuminate\View\View;

class PaginaController extends Controlador
{
    public function inicio(): View
    {
        return view('sede.inicio', [
            'procedimientos' => Procedimiento::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function tramite(Procedimiento $procedimiento): View
    {
        abort_unless($procedimiento->activo, 404);

        $representaciones = auth('sede')->check()
            ? $this->persona()->representacionesComoRepresentante()->where('estado', 'validada')->with('representado')->get()->filter->vigente()
            : collect();

        return view('sede.tramite', compact('procedimiento', 'representaciones'));
    }

    public function accesibilidad(): View
    {
        return view('sede.legal.accesibilidad');
    }

    public function avisoLegal(): View
    {
        return view('sede.legal.aviso-legal');
    }

    public function privacidad(): View
    {
        return view('sede.legal.privacidad');
    }

    public function cookies(): View
    {
        return view('sede.legal.cookies');
    }
}
