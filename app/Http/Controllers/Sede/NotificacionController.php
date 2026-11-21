<?php

namespace App\Http\Controllers\Sede;

use App\Models\Notificacion;
use App\Sede\Notificaciones\Notificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificacionController extends Controlador
{
    public function __construct(private readonly Notificador $notificador) {}

    public function lista(): View
    {
        return view('sede.notificaciones.lista', [
            'notificaciones' => $this->persona()->notificaciones()->with('expediente')->latest('puesta_disposicion_at')->paginate(20),
        ]);
    }

    public function ver(Notificacion $notificacion): View
    {
        abort_unless($notificacion->persona_id === $this->persona()->id, 404);

        return view('sede.notificaciones.ver', ['notificacion' => $notificacion->load(['documento', 'justificante', 'expediente'])]);
    }

    /** Comparecencia: al acceder, la notificación queda practicada. */
    public function acceder(Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->persona_id === $this->persona()->id, 404);
        $this->notificador->acceder($notificacion, $this->persona());
        activity('notificaciones')->causedBy($this->persona())->performedOn($notificacion)->event('comparecencia')->log('Acceso al contenido de la notificación');

        return redirect()->route('sede.notificaciones.ver', $notificacion)->with('aviso', 'Notificación practicada el '.now()->format('d/m/Y \a \l\a\s H:i').'.');
    }
}
