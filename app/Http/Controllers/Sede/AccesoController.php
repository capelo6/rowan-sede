<?php

namespace App\Http\Controllers\Sede;

use App\Models\Persona;
use App\Sede\Identidad\AccesoDenegado;
use App\Sede\Identidad\CertificadoEspanol;
use App\Sede\Identidad\ServicioAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccesoController extends Controlador
{
    public function __construct(private readonly ServicioAcceso $acceso) {}

    public static function simuladoActivo(): bool
    {
        return (bool) config('sede.acceso.simulado') && ! app()->isProduction();
    }

    public function mostrar(): View|RedirectResponse
    {
        if (auth('sede')->check()) {
            return redirect()->route('sede.carpeta');
        }

        return view('sede.acceso', ['simulado' => self::simuladoActivo()]);
    }

    /** Reto de un solo uso que la persona firma con AutoFirma (se entrega en base64). */
    public function reto(): JsonResponse
    {
        return response()->json(['datos' => base64_encode($this->acceso->nuevoReto())]);
    }

    public function acceder(Request $request): RedirectResponse
    {
        $request->validate(['firma' => ['required', 'string', 'max:40000']]);
        try {
            $persona = $this->acceso->acceder((string) $request->input('firma'));
        } catch (AccesoDenegado $e) {
            return back()->withErrors(['acceso' => $e->getMessage()]);
        }

        return $this->entrar($request, $persona);
    }

    /** Acceso de demostración sin certificado. Solo fuera de producción y si se activa expresamente. */
    public function simulado(Request $request): RedirectResponse
    {
        abort_unless(self::simuladoActivo(), 404);
        $datos = $request->validate([
            'nif' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:120'],
        ]);
        $nif = CertificadoEspanol::normalizarNif($datos['nif']);
        if ($nif === null) {
            return back()->withErrors(['nif' => 'El NIF no es válido.'])->withInput();
        }
        $persona = Persona::query()->firstOrNew(['nif' => $nif]);
        $persona->fill(['tipo_persona' => 'fisica', 'nombre' => $datos['nombre'], 'apellido1' => $datos['apellidos'],
            'ultimo_acceso_at' => now(), 'ultimo_certificado' => ['tipo' => 'simulado', 'emisor' => 'Acceso de demostración']])->save();

        return $this->entrar($request, $persona);
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('sede')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sede.inicio')->with('aviso', 'Ha cerrado la sesión.');
    }

    private function entrar(Request $request, Persona $persona): RedirectResponse
    {
        Auth::guard('sede')->login($persona);
        $request->session()->regenerate();
        activity('acceso')->causedBy($persona)->event('acceso')->log('Acceso a la sede');

        return redirect()->intended(route('sede.carpeta'));
    }
}
